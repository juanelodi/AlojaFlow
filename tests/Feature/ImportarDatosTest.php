<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\PaquetePais;
use App\Filament\Pages\ImportarDatos;
use App\Models\Envio;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Reservas\GestorReservas;
use App\Reservas\ImportadorDatos;
use App\Reservas\ImportadorPrecios;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use App\Support\Csv;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es']);
    $this->propiedad->refresh();
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => 'C1', 'nombre' => 'Casa 1', 'capacidad' => 4]);
    $this->importador = app(ImportadorDatos::class);
    $this->gerente = User::query()->create(['name' => 'Ger', 'email' => 'ger@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
    $this->actingAs($this->gerente);
    app()->setLocale('es');
    $this->linea = fn (string $unidad = 'Casa 1', string $llegada = '2027-03-01', string $salida = '2027-03-04', string $nombre = 'Ana', string $correo = 'ana@example.com', string $total = '3000.00', string $pagado = '', string $estado = ''): string => implode(';', [$unidad, $llegada, $salida, '2', $nombre, 'Ejemplo', $correo, '555', $total, $pagado, $estado, 'Directo', 'Hola']);
});

afterEach(fn () => Ajustes::olvidar());

describe('reservas', function (): void {
    it('revisar no cambia nada, y dice qué trae cada línea', function (): void {
        $filas = $this->importador->revisar($this->propiedad, ImportadorDatos::RESERVAS, ($this->linea)());

        expect($filas)->toHaveCount(1)
            ->and($filas[0]['error'])->toBeNull()
            ->and($filas[0]['resumen'])->toContain('Casa 1')->toContain('Ana Ejemplo')
            ->and($filas[0]['resumen'])->toContain(Formato::fecha(CarbonImmutable::parse('2027-03-01')))->toContain(Formato::dinero(300000, 'MXN'))
            ->and(Reserva::query()->count())->toBe(0)
            ->and(Huesped::query()->count())->toBe(0);
    });

    it('importa la reserva con su huésped, su total y lo pagado', function (): void {
        $resultado = $this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, ($this->linea)(pagado: '1000'));

        $reserva = Reserva::query()->sole();
        expect($resultado['importadas'])->toBe(1)
            ->and($resultado['fallidas'])->toBe([])
            ->and($reserva->unidad_id)->toBe($this->unidad->id)
            ->and($reserva->llegada->format('Y-m-d'))->toBe('2027-03-01')
            ->and($reserva->salida->format('Y-m-d'))->toBe('2027-03-04')
            ->and($reserva->total)->toBe(300000)
            ->and($reserva->pagado)->toBe(100000)
            ->and($reserva->estado)->toBe(EstadoReserva::Confirmada)
            ->and($reserva->origen_texto)->toBe('Directo')
            ->and($reserva->comentario)->toBe('Hola')
            ->and($reserva->pagos()->sole()->metodo)->toBe(MetodoPago::Otro)
            ->and($reserva->titular->nombreCompleto())->toBe('Ana Ejemplo')
            ->and($reserva->titular->contactos()->count())->toBe(2)
            ->and($reserva->nochesOcupadas()->count())->toBe(3);
    });

    it('encuentra la unidad por su nombre o su código, sin importar mayúsculas', function (): void {
        $resultado = $this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, ($this->linea)(unidad: 'casa 1')."\n".($this->linea)(unidad: 'c1', llegada: '2027-04-01', salida: '2027-04-02'));

        expect($resultado['importadas'])->toBe(2);
    });

    it('acepta día/mes/año, coma decimal, tabuladores y el encabezado de la plantilla', function (): void {
        $encabezado = implode("\t", $this->importador->plantilla(ImportadorDatos::RESERVAS)[0]);
        $linea = implode("\t", ['Casa 1', '01/03/2027', '04/03/2027', '2', 'Ana', 'Ejemplo', '', '', '3.000,50', '', '', '', '']);

        $resultado = $this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, $encabezado."\n".$linea);

        expect($resultado['importadas'])->toBe(1)
            ->and(Reserva::query()->sole()->total)->toBe(300050);
    });

    it('dice el error de cada línea y deja pasar a las demás', function (): void {
        $texto = implode("\n", [
            ($this->linea)(unidad: 'No existe'),
            ($this->linea)(llegada: 'ayer'),
            ($this->linea)(llegada: '2027-03-04', salida: '2027-03-01'),
            ($this->linea)(nombre: ''),
            ($this->linea)(correo: 'no-es-correo'),
            ($this->linea)(total: 'mucho'),
            ($this->linea)(estado: 'rara'),
            implode(';', ['Casa 1', '2027-05-01', '2027-05-02', '9', 'Ana', 'Ejemplo', '', '', '', '', '', '', '']),
            ($this->linea)(llegada: '2027-06-01', salida: '2027-06-02'),
        ]);

        $resultado = $this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, $texto);

        expect($resultado['importadas'])->toBe(1)
            ->and(array_column($resultado['fallidas'], 'error'))->toBe([
                __('importar.error_unidad'), __('importar.error_fecha', ['formato' => __('importar.formato_dia_primero')]), __('importar.error_orden'), __('importar.error_nombre'), __('importar.error_correo'), __('importar.error_importe'), __('importar.error_estado'), __('ui.reservas.error_capacidad', ['capacidad' => 4]),
            ]);
    });

    it('no pisa lo que ya está, ni dentro del mismo archivo', function (): void {
        $huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Otro']);
        app(GestorReservas::class)->crear($this->propiedad, ['unidad_id' => $this->unidad->id, 'titular_id' => $huesped->id, 'llegada' => '2027-03-02', 'salida' => '2027-03-03', 'personas' => 1, 'total' => 1]);

        $filas = $this->importador->revisar($this->propiedad, ImportadorDatos::RESERVAS, ($this->linea)()."\n".($this->linea)(llegada: '2027-07-01', salida: '2027-07-05')."\n".($this->linea)(llegada: '2027-07-04', salida: '2027-07-06')."\n".($this->linea)(llegada: '2027-07-05', salida: '2027-07-06'));

        expect(array_column($filas, 'error'))->toBe([__('importar.error_ocupada'), null, __('importar.error_ocupada'), null]);
    });

    it('el huésped con el mismo correo y nombre no se repite, pero con otro nombre sí', function (): void {
        $resultado = $this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, implode("\n", [
            ($this->linea)(),
            ($this->linea)(llegada: '2027-04-01', salida: '2027-04-02', nombre: 'ANA'),
            ($this->linea)(llegada: '2027-05-01', salida: '2027-05-02', nombre: 'Otra'),
        ]));

        expect($resultado['importadas'])->toBe(3)
            ->and(Huesped::query()->count())->toBe(2);
    });

    it('sin correo crea su huésped', function (): void {
        $this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, ($this->linea)(correo: ''));

        expect(Reserva::query()->sole()->titular->nombre)->toBe('Ana');
    });

    it('las canceladas se omiten, también escrita en otro idioma', function (): void {
        $filas = $this->importador->revisar($this->propiedad, ImportadorDatos::RESERVAS, ($this->linea)(estado: 'cancelada')."\n".($this->linea)(estado: 'Cancelled', llegada: '2027-04-01', salida: '2027-04-02'));

        expect(array_column($filas, 'aviso'))->toBe([__('importar.cancelada_omitida'), __('importar.cancelada_omitida')]);

        $resultado = $this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, ($this->linea)(estado: 'cancelada'));
        expect($resultado['importadas'])->toBe(0)
            ->and(Reserva::query()->count())->toBe(0);
    });

    it('una sin confirmar entra así', function (): void {
        $this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, ($this->linea)(estado: 'Sin confirmar'));

        expect(Reserva::query()->sole()->estado)->toBe(EstadoReserva::SinConfirmar);
    });

    it('no manda mensajes de reserva creada', function (): void {
        App\Models\Plantilla::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'hola', 'nombre' => 'Hola', 'asunto' => ['es' => 'Hola'], 'cuerpo' => ['es' => 'Hola {huesped}']]);
        App\Models\Automatizacion::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Al crear', 'disparador' => App\Enums\DisparadorAutomatizacion::ReservaCreada, 'accion' => App\Enums\AccionAutomatizacion::CorreoHuesped, 'plantilla_id' => App\Models\Plantilla::query()->value('id'), 'canal' => App\Enums\CanalEnvio::Correo, 'activa' => true]);

        $this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, ($this->linea)());

        $this->artisan('alojaflow:mensajes')->assertSuccessful();
        expect(Envio::query()->count())->toBe(0);
    });

    it('en Estados Unidos lee mes/día/año, y lo que no se entiende dice su error', function (): void {
        $this->propiedad->update(['paquete_pais' => PaquetePais::EstadosUnidos]);
        $fila = $this->importador->revisar($this->propiedad->refresh(), ImportadorDatos::RESERVAS, ($this->linea)(llegada: '03/15/2027', salida: '03/18/2027'))[0];

        expect($fila['error'])->toBeNull()
            ->and($fila['llegada_fecha'])->toBe('2027-03-15')
            ->and($fila['salida_fecha'])->toBe('2027-03-18')
            ->and($this->importador->revisar($this->propiedad, ImportadorDatos::RESERVAS, ($this->linea)(llegada: '15/03/2027', salida: '18/03/2027'))[0]['error'])
            ->toBe(__('importar.error_fecha', ['formato' => __('importar.formato_mes_primero')]));
    });

    it('una fecha mala en la primera línea no pasa por encabezado', function (): void {
        $filas = $this->importador->revisar($this->propiedad, ImportadorDatos::RESERVAS, ($this->linea)(llegada: '03/15/2027', salida: '03/18/2027'));

        expect($filas)->toHaveCount(1)
            ->and($filas[0]['error'])->toBe(__('importar.error_fecha', ['formato' => __('importar.formato_dia_primero')]));
    });

    it('lo pagado entra con la fecha de llegada, o la de hoy si aún no llega', function (): void {
        $this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, ($this->linea)(llegada: '2020-03-01', salida: '2020-03-04', pagado: '1000')."\n".($this->linea)(llegada: '2090-03-01', salida: '2090-03-04', pagado: '1000'));

        $fechas = Reserva::query()->orderBy('llegada')->get()->map(fn (Reserva $reserva): string => $reserva->pagos()->sole()->fecha->format('Y-m-d'))->all();

        expect($fechas)->toBe(['2020-03-01', $this->propiedad->hoy()->format('Y-m-d')]);
    });

    it('una cancelada se omite aunque su unidad ya no exista', function (): void {
        $fila = $this->importador->revisar($this->propiedad, ImportadorDatos::RESERVAS, ($this->linea)(unidad: 'Ya no está', estado: 'cancelada'))[0];

        expect($fila['error'])->toBeNull()
            ->and($fila['aviso'])->toBe(__('importar.cancelada_omitida'));
    });

    it('después de importar solo queda lo que falló, con su encabezado', function (): void {
        $encabezado = implode(';', $this->importador->plantilla(ImportadorDatos::RESERVAS)[0]);
        $mala = ($this->linea)(unidad: 'No existe', llegada: '2027-05-01', salida: '2027-05-02', correo: '');

        $resultado = $this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, $encabezado."\r\n".($this->linea)(correo: '')."\r\n".$mala."\r\n".($this->linea)(llegada: '2027-04-01', salida: '2027-04-02', correo: ''));

        expect($resultado['importadas'])->toBe(2)
            ->and($resultado['pendiente'])->toBe($encabezado."\n".$mala);

        $this->unidad->update(['nombre' => 'No existe']);

        expect($this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, $resultado['pendiente'])['importadas'])->toBe(1)
            ->and(Reserva::query()->count())->toBe(3)
            ->and(Huesped::query()->count())->toBe(3);
    });

    it('sin fallas no queda nada, y con demasiado se queda todo', function (): void {
        $muchas = implode("\n", array_fill(0, ImportadorDatos::FILAS_MAXIMAS + 1, ($this->linea)()));

        expect($this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, ($this->linea)())['pendiente'])->toBe('')
            ->and($this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, $muchas)['pendiente'])->toBe($muchas);
    });

    it('un dato que la base no acepta no detiene a las demás, y no deja rastro', function (): void {
        $this->unidad->update(['capacidad' => 0]);
        $enorme = implode(';', ['Casa 1', '2027-05-01', '2027-05-02', '99999999999', 'Beto', 'Ejemplo', '', '', '', '', '', '', '']);

        $resultado = $this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, $enorme."\n".($this->linea)());

        expect($resultado['importadas'])->toBe(1)
            ->and($resultado['fallidas'][0]['linea'])->toBe(1)
            ->and($resultado['fallidas'][0]['error'])->toBe(__('importar.error_guardar'))
            ->and(Huesped::query()->sole()->nombre)->toBe('Ana');
    });

    it('lo larguísimo se corta', function (): void {
        $linea = implode(';', ['Casa 1', '2027-05-01', '2027-05-02', '2', 'Ana', 'Ejemplo', '', str_repeat('5', 300), '', '', '', str_repeat('o', 300), str_repeat('x', 70000)]);

        expect($this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, $linea)['importadas'])->toBe(1)
            ->and(mb_strlen((string) Reserva::query()->sole()->comentario))->toBe(5000);
    });

    it('queda anotado en la auditoría, sin los datos', function (): void {
        $this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, ($this->linea)());

        expect(RegistroAuditoria::query()->where('accion', 'importar.reservas')->sole()->cambios)->toEqual(['importadas' => 1, 'fallidas' => 0]);
    });
});

describe('huéspedes', function (): void {
    it('importa con sus datos y contactos', function (): void {
        $texto = implode("\n", array_map(fn (array $f): string => implode(';', $f), [
            ['Nombre', 'Apellido', 'Segundo apellido', 'Correo', 'Teléfono', 'Nacionalidad', 'Tipo', 'Número', 'Nacimiento', 'Notas'],
            ['Ana', 'Ejemplo', 'Prueba', 'ana@example.com', '+52 55 5550 0101', 'México', 'INE', 'ABC123', '20/05/1990', 'Prefiere planta baja'],
        ]));

        $resultado = $this->importador->importar($this->propiedad, ImportadorDatos::HUESPEDES, $texto);

        $huesped = Huesped::query()->sole();
        expect($resultado['importadas'])->toBe(1)
            ->and($huesped->nombreCompleto())->toBe('Ana Ejemplo Prueba')
            ->and($huesped->nacionalidad_texto)->toBe('México')
            ->and($huesped->documento_tipo_texto)->toBe('INE')
            ->and($huesped->documento_numero)->toBe('ABC123')
            ->and($huesped->fecha_nacimiento->format('Y-m-d'))->toBe('1990-05-20')
            ->and($huesped->notas)->toBe('Prefiere planta baja')
            ->and($huesped->contactos()->count())->toBe(2);
    });

    it('no repite a quien ya existe, ni dentro del archivo', function (): void {
        $this->importador->importar($this->propiedad, ImportadorDatos::HUESPEDES, 'Ana;Ejemplo;;ana@example.com');

        $filas = $this->importador->revisar($this->propiedad, ImportadorDatos::HUESPEDES, "Ana;Ejemplo;;ANA@example.com\nBeto;Ejemplo;;beto@example.com\nBeto;Ejemplo;;beto@example.com");

        expect(array_column($filas, 'aviso'))->toBe([__('importar.ya_existe'), null, __('importar.ya_existe')]);

        $this->importador->importar($this->propiedad, ImportadorDatos::HUESPEDES, "Ana;Ejemplo;;ANA@example.com\nBeto;Ejemplo;;beto@example.com\nBeto;Ejemplo;;beto@example.com");
        expect(Huesped::query()->count())->toBe(2);
    });

    it('con el mismo correo y otro nombre es otra persona, también dentro del archivo', function (): void {
        $texto = "Ana;Ejemplo;;familia@example.com\nBeto;Ejemplo;;familia@example.com\nAna;Ejemplo;;familia@example.com";

        expect(array_column($this->importador->revisar($this->propiedad, ImportadorDatos::HUESPEDES, $texto), 'aviso'))->toBe([null, null, __('importar.ya_existe')]);

        $this->importador->importar($this->propiedad, ImportadorDatos::HUESPEDES, $texto);

        expect(Huesped::query()->count())->toBe(2);
    });

    it('una nota con saltos de línea es una sola fila, y el número dice dónde empieza', function (): void {
        $texto = "Ana;Ejemplo;;ana@example.com;;;;;;\"Primera\r\nSegunda\"\r\nLuis;Prueba;;luis@example.com";

        expect(array_column($this->importador->revisar($this->propiedad, ImportadorDatos::HUESPEDES, $texto), 'linea'))->toBe([1, 3])
            ->and($this->importador->importar($this->propiedad, ImportadorDatos::HUESPEDES, $texto)['importadas'])->toBe(2)
            ->and(Huesped::query()->where('nombre', 'Ana')->sole()->notas)->toBe("Primera\nSegunda");
    });

    it('las filas de puros separadores no cuentan, ni antes del encabezado', function (): void {
        $encabezado = implode(';', $this->importador->plantilla(ImportadorDatos::HUESPEDES)[0]);

        expect(array_column($this->importador->revisar($this->propiedad, ImportadorDatos::HUESPEDES, ";;;;\n{$encabezado}\nAna;Ejemplo\n;;;;;;;;;\nLuis;Prueba"), 'linea'))->toBe([3, 5]);
    });

    it('unas comillas sin cerrar se avisan, en vez de perder lo que sigue', function (): void {
        $texto = "Ana;Ejemplo\nBeto;\"Sin cerrar\nLuis;Prueba\nCarla;Prueba";

        $resultado = $this->importador->importar($this->propiedad, ImportadorDatos::HUESPEDES, $texto);

        expect($resultado['importadas'])->toBe(1)
            ->and($resultado['fallidas'][0]['linea'])->toBe(2)
            ->and($resultado['fallidas'][0]['error'])->toBe(__('importar.error_comillas'))
            ->and($resultado['pendiente'])->toBe("Beto;\"Sin cerrar\nLuis;Prueba\nCarla;Prueba");
    });

    it('una comilla en medio de un dato no es problema', function (): void {
        $filas = $this->importador->revisar($this->propiedad, ImportadorDatos::HUESPEDES, "Ana \"la jefa\";Ejemplo\nLuis;Prueba");

        expect(array_column($filas, 'error'))->toBe([null, null])
            ->and($filas[0]['nombre'])->toBe('Ana "la jefa"');
    });

    it('dice los errores', function (): void {
        $filas = $this->importador->revisar($this->propiedad, ImportadorDatos::HUESPEDES, ";Ejemplo\nAna;;;no-es-correo\nAna;;;;;;;;mañana");

        expect(array_column($filas, 'error'))->toBe([__('importar.error_nombre'), __('importar.error_correo'), __('importar.error_fecha', ['formato' => __('importar.formato_dia_primero')])]);
    });

    it('lo que viene protegido del CSV exportado se lee sin la comilla', function (): void {
        $this->importador->importar($this->propiedad, ImportadorDatos::HUESPEDES, "'=Ana;Ejemplo");

        expect(Huesped::query()->sole()->nombre)->toBe('=Ana');
    });
});

describe('límites y plantilla', function (): void {
    it('se niega con demasiadas filas o demasiado texto', function (): void {
        $muchas = implode("\n", array_fill(0, ImportadorDatos::FILAS_MAXIMAS + 1, 'Ana;Ejemplo'));

        $filas = $this->importador->revisar($this->propiedad, ImportadorDatos::HUESPEDES, $muchas);

        expect($filas)->toHaveCount(1)
            ->and($filas[0]['error'])->toBe(__('importar.error_tamano', ['filas' => ImportadorDatos::FILAS_MAXIMAS]))
            ->and($this->importador->revisar($this->propiedad, ImportadorDatos::HUESPEDES, str_repeat('x', ImportadorDatos::BYTES_MAXIMOS + 1))[0]['error'])->not->toBeNull();
    });

    it('la plantilla se puede volver a importar tal cual', function (): void {
        $texto = implode("\n", array_map(fn (array $f): string => implode(';', $f), $this->importador->plantilla(ImportadorDatos::HUESPEDES)));

        expect($this->importador->importar($this->propiedad, ImportadorDatos::HUESPEDES, $texto)['importadas'])->toBe(1);

        $this->unidad->update(['codigo' => '101']);
        $texto = implode("\n", array_map(fn (array $f): string => implode(';', $f), $this->importador->plantilla(ImportadorDatos::RESERVAS)));
        expect($this->importador->importar($this->propiedad, ImportadorDatos::RESERVAS, $texto)['importadas'])->toBe(1);
    });

    it('las plantillas salen para Excel y se pueden volver a leer', function (): void {
        $archivo = Csv::paraExcel($this->importador->plantilla(ImportadorDatos::HUESPEDES));

        expect(substr($archivo, 0, 2))->toBe("\xFF\xFE")
            ->and(Csv::enUtf8($archivo))->toContain("\t")
            ->and($this->importador->importar($this->propiedad, ImportadorDatos::HUESPEDES, $archivo)['importadas'])->toBe(1)
            ->and(Huesped::query()->sole()->apellido1)->toBe(__('importar.ejemplo.apellido'))
            ->and(Csv::enUtf8(Csv::paraExcel([['=1+1', 'Hola']])))->toBe("'=1+1\tHola\r\n");
    });

    it('importar precios lee las fechas según el país', function (): void {
        $this->propiedad->update(['paquete_pais' => PaquetePais::EstadosUnidos]);
        $precios = app(ImportadorPrecios::class);

        expect($precios->revisar($this->propiedad->refresh(), 'Doble;03/15/2027;03/20/2027;1200')[0]['desde'])->toBe('2027-03-15')
            ->and($precios->revisar($this->propiedad, 'Doble;15/03/2027;20/03/2027;1200')[0]['error'])->toBe(__('operacion.precios.error_fecha', ['formato' => __('importar.formato_mes_primero')]));
    });

    it('importar precios encuentra una tarifa que el archivo trae protegida', function (): void {
        $this->tarifa->update(['nombre' => '-20% ocupación']);

        expect(app(ImportadorPrecios::class)->revisar($this->propiedad, Csv::paraExcel([['Tarifa', 'Desde', 'Hasta', 'Precio'], ['-20% ocupación', '2027-03-01', '2027-03-20', '1200']])))->toHaveCount(1)
            ->and(app(ImportadorPrecios::class)->revisar($this->propiedad, Csv::paraExcel([['-20% ocupación', '2027-03-01', '2027-03-20', '1200']]))[0]['tarifa_id'])->toBe($this->tarifa->id);
    });

    it('el importar precios sigue leyendo fechas e importes', function (): void {
        expect(Csv::fecha('2027-02-30'))->toBeNull()
            ->and(Csv::fecha('28/02/2027'))->toBe('2027-02-28')
            ->and(Csv::fecha('02/28/2027', true))->toBe('2027-02-28')
            ->and(Csv::fecha('28/02/2027', true))->toBeNull()
            ->and(Csv::importe('1,200', 'MXN'))->toBe(120000)
            ->and(Csv::importe('1.200,50', 'MXN'))->toBe(120050)
            ->and(Csv::importe('-5', 'MXN'))->toBeNull();
    });
});

describe('la pantalla', function (): void {
    it('revisa, importa con copia de seguridad, y deja las que fallaron', function (): void {
        $texto = ($this->linea)()."\n".($this->linea)(unidad: 'No existe');

        Livewire::actingAs($this->gerente)->test(ImportarDatos::class)
            ->assertSet('propiedadId', $this->propiedad->id)
            ->call('revisar', $texto)
            ->assertSee(__('importar.correcta'))
            ->assertSee(__('importar.error_unidad'))
            ->assertSee(trans_choice('importar.resumen', 1, ['n' => 1, 'total' => 2]))
            ->call('importar', $texto)
            ->assertReturned(($this->linea)(unidad: 'No existe'))
            ->assertNotified()
            ->assertSee(__('importar.error_unidad'))
            ->assertDontSee(__('importar.correcta'));

        expect(Reserva::query()->count())->toBe(1)
            ->and(collect(app(App\Copias\GestorCopias::class)->listar())->pluck('origen')->all())->toContain('antes-de-importar');
    });

    it('cambia a huéspedes, y solo acepta lo que conoce', function (): void {
        Livewire::actingAs($this->gerente)->test(ImportarDatos::class)
            ->set('tipo', ImportadorDatos::HUESPEDES)
            ->assertSee(__('importar.ayuda_huespedes'))
            ->set('tipo', 'otra_cosa')
            ->assertSet('tipo', ImportadorDatos::RESERVAS);
    });

    it('baja la plantilla', function (): void {
        Livewire::actingAs($this->gerente)->test(ImportarDatos::class)
            ->call('descargarPlantilla')
            ->assertFileDownloaded('importar-reservas.csv', Csv::paraExcel($this->importador->plantilla(ImportadorDatos::RESERVAS)));
    });

    it('sin archivo no hace nada', function (): void {
        Livewire::actingAs($this->gerente)->test(ImportarDatos::class)->call('revisar', '  ')->assertSet('filas', [])->call('importar', '  ');

        expect(collect(app(App\Copias\GestorCopias::class)->listar()))->toHaveCount(0);
    });

    it('no está en la demostración pública', function (): void {
        expect(ImportarDatos::canAccess())->toBeTrue();

        config(['alojaflow.modo_demo' => true]);

        expect(ImportarDatos::canAccess())->toBeFalse();
    });

    it('un recepcionista no entra, ni con otra propiedad, ni en una subordinada', function (): void {
        $recepcion = User::query()->create(['name' => 'Rec', 'email' => 'rec@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
        Acceso::asignarRoles($recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
        $this->actingAs($recepcion);
        expect(ImportarDatos::canAccess())->toBeFalse();

        $subordinada = Propiedad::query()->create(['nombre' => 'Sub', 'codigo' => 'sub', 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
        $otro = User::query()->create(['name' => 'Otro', 'email' => 'otro@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
        Acceso::asignarRoles($otro, [['propiedad_id' => $subordinada->id, 'rol' => 'gerente']]);
        $this->actingAs($otro);
        expect(ImportarDatos::canAccess())->toBeFalse();

        Livewire::actingAs($this->gerente)->test(ImportarDatos::class)->set('propiedadId', $subordinada->id)->assertSet('filas', [])->tap(fn ($pagina) => expect($pagina->instance()->propiedadElegida())->toBeNull());
    });
});
