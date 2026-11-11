<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Tests\Support;

use DateTimeImmutable;

/**
 * Escribe copias sintéticas con el formato de las de HotelDruid (subordinacion.md, parte B), con datos inventados,
 * para probar la sincronización sin una principal ni datos reales (regla 12). Solo lleva las columnas que la
 * especificación nombra; `fila()` admite cualquier otra.
 */
final class ConstructorCopia
{
    private const ALTA = '2026-01-15 09:00:00';

    /** Tablas que no dependen del año, con sus columnas. Las últimas se descartan al leer: están para comprobarlo. */
    private const GLOBALES = [
        'anni' => ['idanni', 'tipo_periodi'],
        'versioni' => ['idversioni', 'num_versione'],
        'appartamenti' => ['idappartamenti', 'numpiano', 'numcasa', 'maxoccupanti', 'priorita', 'priorita2', 'app_vicini', 'letto', 'commento'],
        'clienti' => [
            'idclienti', 'cognome', 'cognome2', 'nome', 'soprannome', 'titolo', 'sesso', 'lingua', 'datanascita', 'cittanascita', 'regionenascita',
            'nazionenascita', 'nazionalita', 'documento', 'tipodoc', 'scadenzadoc', 'cittadoc', 'regionedoc', 'nazionedoc', 'nazione', 'regione',
            'citta', 'via', 'numcivico', 'cap', 'telefono', 'telefono2', 'telefono3', 'fax', 'email', 'email2', 'email3', 'cod_fiscale',
            'partita_iva', 'commento', 'idclienti_compagni', 'doc_inviati', 'max_num_ordine', 'datainserimento', 'hostinserimento', 'utente_inserimento',
        ],
        'relclienti' => ['idclienti', 'numero', 'tipo', 'testo1', 'testo2', 'testo3'],
        'personalizza' => ['idpersonalizza', 'idutente', 'valpersonalizza'],
        'nazioni' => ['idnazioni', 'nome_nazione'],
        'regioni' => ['idregioni', 'nome_regione'],
        'citta' => ['idcitta', 'nome_citta'],
        'documentiid' => ['iddocumentiid', 'nome_documentoid'],
        'parentele' => ['idparentele', 'nome_parentela'],
        'descrizioni' => ['nome', 'tipo', 'lingua', 'numero', 'testo'],
        'casse' => ['idcasse', 'nome_cassa'],
        'beniinventario' => ['idbeniinventario', 'nome_bene'],
        'magazzini' => ['idmagazzini', 'nome_magazzino'],
        'relinventario' => ['idbeneinventario', 'idappartamento', 'idmagazzino', 'quantita', 'scadenza'],
        'utenti' => ['idutenti', 'nome_utente', 'password'],
        'privilegi' => ['idutente', 'anno'],
        'sessioni' => ['idsessioni', 'idutente'],
        'contratti' => ['numero', 'tipo', 'testo'],
        'interconnessioni' => ['idlocale', 'tipoid', 'nome_ic'],
    ];

    /** Tablas con el año pegado al nombre. */
    private const ANUALES = [
        'prenota' => self::RESERVA,
        'prenotacanc' => self::RESERVA,
        'costiprenota' => ['idcostiprenota', 'idprenota', 'tipo', 'nome', 'valore', 'valore_perc', 'associasett', 'settimane', 'moltiplica', 'categoria', 'idntariffe'],
        'rclientiprenota' => ['idprenota', 'idclienti', 'num_ordine', 'parentela'],
        'periodi' => ['idperiodi', 'datainizio', 'datafine', 'tariffa1'],
        'ntariffe' => ['idntariffe', 'nomecostoagg'],
        'regole' => ['idregole', 'app_agenzia', 'motivazione', 'motivazione2', 'iddatainizio', 'iddatafine'],
        'soldi' => ['idsoldi', 'motivazione', 'metodo_pagamento', 'saldo_prenota', 'data_transazione'],
        'costi' => ['idcosti', 'nome_costo', 'val_costo', 'tipo_costo', 'nome_cassa', 'provenienza_costo', 'metodo_pagamento', 'data_transazione'],
    ];

    private const RESERVA = [
        'idprenota', 'idclienti', 'idappartamenti', 'iddatainizio', 'iddatafine', 'num_persone', 'cat_persone', 'tariffa', 'tariffesettimanali',
        'sconto', 'tariffa_tot', 'caparra', 'commissioni', 'tasseperc', 'pagato', 'valuta', 'metodo_pagamento', 'codice', 'origine', 'commento',
        'conferma', 'checkin', 'checkout', 'assegnazioneapp', 'app_assegnabili', 'idprenota_compagna', 'id_anni_prec', 'incompatibilita',
        'datainserimento', 'data_modifica', 'hostinserimento', 'utente_inserimento',
    ];

    private string $version = '3.081';

    private bool $inicioSesion = true;

    private bool $cortada = false;

    /** @var list<int> */
    private array $anios = [2026];

    /** @var array<string, true> */
    private array $omitidas = [];

    /** @var array<string, list<array<string, mixed>>> filas por tabla, con el nombre que lleva en la copia */
    private array $filas = [];

    /** @var array<int, int> año => hasta qué periodo llega su calendario, si pasa del 31 de diciembre */
    private array $periodos = [];

    public static function nueva(): self
    {
        return (new self())
            ->fila('personalizza', ['idpersonalizza' => 'valuta', 'idutente' => 1, 'valpersonalizza' => '$'])
            ->fila('personalizza', ['idpersonalizza' => 'tema', 'idutente' => 2, 'valpersonalizza' => 'oscuro'])
            ->fila('utenti', ['idutenti' => 1, 'nome_utente' => 'admin', 'password' => 'hash-inventado-de-una-contraseña'])
            ->fila('contratti', ['numero' => 1, 'tipo' => 'api', 'testo' => 'clave-inventada-de-api']);
    }

    public function version(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function sinInicioSesion(): self
    {
        $this->inicioSesion = false;

        return $this;
    }

    /** La copia se queda a medias, sin su final. */
    public function cortada(): self
    {
        $this->cortada = true;

        return $this;
    }

    public function anios(int ...$anios): self
    {
        $this->anios = array_values($anios);

        return $this;
    }

    /** Con el nombre que lleva en la copia: `clienti`, `soldi2026`… */
    public function omitirTabla(string $tabla): self
    {
        $this->omitidas[$tabla] = true;

        return $this;
    }

    public function unidad(string $codigo, int $capacidad = 2, int $prioridad = 1): self
    {
        return $this->fila('appartamenti', ['idappartamenti' => $codigo, 'maxoccupanti' => $capacidad, 'priorita' => $prioridad]);
    }

    /**
     * @param  array<string, mixed>  $extra  otras columnas de `clienti`
     */
    public function cliente(int $id, string $apellido, ?string $nombre = null, array $extra = []): self
    {
        return $this->fila('clienti', $extra + ['idclienti' => $id, 'cognome' => $apellido, 'nome' => $nombre, 'datainserimento' => self::ALTA]);
    }

    /**
     * Una reserva de $llegada a $salida (la salida no cuenta como noche), en `prenotaAAAA` o, cancelada, en `prenotacancAAAA`.
     *
     * @param  array<string, mixed>  $extra  otras columnas de la reserva
     */
    public function reserva(int $anio, int $id, string $unidad, int $cliente, string $llegada, string $salida, array $extra = [], bool $cancelada = false): self
    {
        $ultimaNoche = (new DateTimeImmutable($salida))->modify('-1 day')->format('Y-m-d');

        return $this->fila(($cancelada ? 'prenotacanc' : 'prenota').$anio, $extra + [
            'idprenota' => $id,
            'idclienti' => $cliente,
            'idappartamenti' => $unidad,
            'iddatainizio' => $this->idPeriodo($anio, $llegada),
            'iddatafine' => $this->idPeriodo($anio, $ultimaNoche),
            'num_persone' => 2,
            'tariffa_tot' => '1500.00',
            'pagato' => '0',
            'conferma' => 'S',
            'datainserimento' => self::ALTA,
        ]);
    }

    /**
     * Una fila de cualquier tabla. Las columnas que la tabla no tenga se le añaden.
     *
     * @param  array<string, mixed>  $fila
     */
    public function fila(string $tabla, array $fila): self
    {
        $this->filas[$tabla][] = $fila;
        if (preg_match('/^[a-z]+(\d{4})$/', $tabla, $partes) === 1 && ! in_array((int) $partes[1], $this->anios, true)) {
            $this->anios[] = (int) $partes[1];
        }

        return $this;
    }

    public function texto(): string
    {
        $lineas = [
            '<?php exit(); ?>', '', '<!--             2026-11-03 10:15:00             -->', '',
            '<!--  **    SAVE THIS FILE AS hoteld_backup.php     **  -->', '',
            '<!--  **  SALVA QUESTO FILE COME hoteld_backup.php  **  -->', '', '',
            '<backup>', '<versione>'.$this->version.'</versione>', '<log>NO</log>',
        ];
        foreach ($this->archivos() as $nombre => $contenido) {
            array_push($lineas, '<file>', '<nomefile>./dati/'.$nombre.'</nomefile>', '<contenuto>', $contenido.'</contenuto>', '</file>');
        }
        $lineas[] = '<database>';
        foreach ($this->tablas() as $nombre => [$columnas, $filas]) {
            if (! isset($this->omitidas[$nombre])) {
                array_push($lineas, ...$this->tabla($nombre, $columnas, $filas));
            }
        }
        if (! $this->cortada) {
            array_push($lineas, '</database>', '</backup>');
        }

        return implode("\n", $lineas)."\n";
    }

    public function gzip(): string
    {
        return (string) gzencode($this->texto(), 9);
    }

    public function guardar(string $ruta): string
    {
        file_put_contents($ruta, $this->texto());

        return $ruta;
    }

    /**
     * El periodo de un día en el calendario de un año. Los periodos son de un día y se numeran desde el 1 de enero;
     * una fecha del año siguiente alarga el calendario, como en una estancia que cruza el fin de año.
     */
    public function idPeriodo(int $anio, string $fecha): int
    {
        $id = (int) (new DateTimeImmutable($anio.'-01-01'))->diff(new DateTimeImmutable($fecha))->format('%r%a') + 1;
        $this->periodos[$anio] = max($this->periodos[$anio] ?? 0, $id);

        return $id;
    }

    /**
     * Los archivos de configuración de la principal que van en la copia, escritos en PHP: nunca se ejecutan.
     *
     * @return array<string, string>
     */
    private function archivos(): array
    {
        $archivos = [
            'lingua.php' => "<?php\n\$lingua[1] = \"es\";\n?>",
            'versione.php' => "<?php\ndefine('C_VERSIONE_ATTUALE', ".$this->version.");\n?>",
            'dati_interconnessioni.php' => "<?php\n\$clave_de_un_canal = \"secreto-inventado\";\n?>",
        ];
        if ($this->inicioSesion) {
            $archivos['abilita_login'] = '';
        }

        return $archivos;
    }

    /**
     * Todas las tablas en el orden de la copia: las globales y después las de cada año.
     *
     * @return array<string, array{0: list<string>, 1: list<array<string, mixed>>}>
     */
    private function tablas(): array
    {
        $anios = $this->anios;
        sort($anios);
        $porOmision = [
            'anni' => array_map(fn (int $anio): array => ['idanni' => $anio, 'tipo_periodi' => 'g'], $anios),
            'versioni' => [['idversioni' => 1, 'num_versione' => $this->version]],
        ];

        $tablas = [];
        foreach (self::GLOBALES as $nombre => $columnas) {
            // La 3.08 no tiene la columna de caducidad del inventario (A1).
            if ($nombre === 'relinventario' && $this->version === '3.08') {
                $columnas = array_values(array_diff($columnas, ['scadenza']));
            }
            $tablas[$nombre] = [$columnas, [...($porOmision[$nombre] ?? []), ...($this->filas[$nombre] ?? [])]];
        }
        foreach ($anios as $anio) {
            foreach (self::ANUALES as $nombre => $columnas) {
                $filas = $nombre === 'periodi' ? $this->calendario($anio) : [];
                $tablas[$nombre.$anio] = [$columnas, [...$filas, ...($this->filas[$nombre.$anio] ?? [])]];
            }
        }
        // Lo que se haya añadido con fila() a una tabla que no está en las listas.
        foreach ($this->filas as $nombre => $filas) {
            $tablas[$nombre] ??= [[], $filas];
        }

        return $tablas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function calendario(int $anio): array
    {
        $primero = new DateTimeImmutable($anio.'-01-01');
        $dias = max((int) $primero->format('L') === 1 ? 366 : 365, $this->periodos[$anio] ?? 0);
        $periodos = [];
        for ($i = 0; $i < $dias; $i++) {
            $dia = $primero->modify('+'.$i.' days');
            $periodos[] = ['idperiodi' => $i + 1, 'datainizio' => $dia->format('Y-m-d'), 'datafine' => $dia->modify('+1 day')->format('Y-m-d'), 'tariffa1' => '1000'];
        }

        return $periodos;
    }

    /**
     * @param  list<string>  $columnas
     * @param  list<array<string, mixed>>  $filas
     * @return list<string>
     */
    private function tabla(string $nombre, array $columnas, array $filas): array
    {
        foreach ($filas as $fila) {
            $columnas = array_values(array_unique([...$columnas, ...array_keys($fila)]));
        }
        $lineas = ['<tabella>', '<nometabella>'.$nombre.'</nometabella>', '<colonnetabella>'];
        foreach ($columnas as $columna) {
            array_push($lineas, '<nomecolonna>'.$columna.'</nomecolonna>', '<tipocolonna>string</tipocolonna>');
        }
        array_push($lineas, '</colonnetabella>', '<righetabella>');
        foreach ($filas as $fila) {
            $valores = array_map(fn (string $columna): string => $this->escapar((string) ($fila[$columna] ?? '')), $columnas);
            // Sobre la fila completa, lo que podría confundirse con el final de la fila o de la tabla (B3).
            $cuerpo = str_replace(['</righetabella>', '</riga>', '<riga>'], ['</righetabella@%&@>', '</riga@%&@>', '<riga@%&@>'], implode('</cmp><cmp>', $valores));
            $lineas[] = '<riga><cmp>'.$cuerpo.'</cmp></riga>';
        }
        array_push($lineas, '</righetabella>', '</tabella>');

        return $lineas;
    }

    /**
     * Lo que la principal escapa en cada valor, en su orden (B3).
     */
    private function escapar(string $valor): string
    {
        return str_replace('</cmp>', '</cmp@%&@>', str_replace('@%&@', '@%&@@%&@', $valor));
    }
}
