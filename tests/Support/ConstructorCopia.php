<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Tests\Support;

use Carbon\CarbonImmutable;

/**
 * Genera copias sintéticas con el formato de HotelDruid 3.081 (subordinacion.md B), con datos
 * inventados. Escribe los escapes igual que crea_backup.php para probar el lector de verdad.
 */
final class ConstructorCopia
{
    private const COLUMNAS = [
        'anni' => ['idanni', 'tipo_periodi'],
        'appartamenti' => ['idappartamenti', 'numpiano', 'maxoccupanti', 'numcasa', 'app_vicini', 'priorita', 'priorita2', 'letto', 'commento'],
        'clienti' => ['idclienti', 'cognome', 'cognome2', 'nome', 'soprannome', 'sesso', 'titolo', 'lingua', 'datanascita', 'cittanascita', 'regionenascita', 'nazionenascita', 'documento', 'scadenzadoc', 'tipodoc', 'cittadoc', 'regionedoc', 'nazionedoc', 'nazionalita', 'nazione', 'regione', 'citta', 'via', 'numcivico', 'cap', 'telefono', 'telefono2', 'telefono3', 'fax', 'email', 'email2', 'email3', 'cod_fiscale', 'partita_iva', 'commento', 'max_num_ordine', 'idclienti_compagni', 'doc_inviati', 'datainserimento', 'hostinserimento', 'utente_inserimento'],
        'relclienti' => ['idclienti', 'numero', 'tipo', 'testo1', 'testo2', 'testo3'],
        'personalizza' => ['idpersonalizza', 'idutente', 'valpersonalizza', 'valpersonalizza_num'],
        'versioni' => ['idversioni', 'num_versione'],
        'utenti' => ['idutenti', 'nome_utente', 'password', 'salt', 'tipo_pass', 'datainserimento', 'hostinserimento'],
        'parentele' => ['idparentele', 'nome_parentela', 'codice_parentela'],
        'prenota' => ['idprenota', 'idclienti', 'idappartamenti', 'iddatainizio', 'iddatafine', 'assegnazioneapp', 'app_assegnabili', 'num_persone', 'cat_persone', 'idprenota_compagna', 'tariffa', 'tariffesettimanali', 'incompatibilita', 'sconto', 'tariffa_tot', 'caparra', 'commissioni', 'tasseperc', 'pagato', 'valuta', 'metodo_pagamento', 'codice', 'origine', 'commento', 'conferma', 'checkin', 'checkout', 'id_anni_prec', 'datainserimento', 'hostinserimento', 'data_modifica', 'utente_inserimento'],
        'costiprenota' => ['idcostiprenota', 'idprenota', 'tipo', 'nome', 'valore', 'associasett', 'settimane'],
        'rclientiprenota' => ['idprenota', 'idclienti', 'num_ordine', 'parentela'],
        'periodi' => ['idperiodi', 'datainizio', 'datafine', 'tariffa1'],
        'ntariffe' => ['idntariffe', 'nomecostoagg'],
        'regole' => ['idregole', 'app_agenzia'],
        'soldi' => ['idsoldi', 'motivazione', 'saldo_prenota'],
        'costi' => ['idcosti', 'nome_costo', 'val_costo'],
    ];

    private const GLOBALES = ['anni', 'appartamenti', 'clienti', 'relclienti', 'personalizza', 'versioni', 'utenti', 'parentele'];

    private const ANUALES = ['prenota', 'prenotacanc', 'costiprenota', 'rclientiprenota', 'periodi', 'ntariffe', 'regole', 'soldi', 'costi'];

    private string $version = '3.081';

    private bool $inicioSesion = true;

    private bool $cortada = false;

    /** @var list<int> */
    private array $anios = [2026];

    /** @var array<string, list<array<string, string|int|null>>> */
    private array $filas = [];

    /** @var list<string> */
    private array $tablasOmitidas = [];

    public static function nueva(): self
    {
        return (new self())
            ->fila('versioni', ['idversioni' => 1, 'num_versione' => '3.081'])
            ->fila('personalizza', ['idpersonalizza' => 'valuta', 'idutente' => 1, 'valpersonalizza' => '$'])
            ->fila('utenti', ['idutenti' => 1, 'nome_utente' => 'admin', 'password' => '$2y$10$hashinventado', 'salt' => '', 'tipo_pass' => '5'])
            ->fila('parentele', ['idparentele' => 1, 'nome_parentela' => 'Hijo']);
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

    public function omitirTabla(string $tabla): self
    {
        $this->tablasOmitidas[] = $tabla;

        return $this;
    }

    public function unidad(string $codigo, int $capacidad = 2, int $prioridad = 1): self
    {
        return $this->fila('appartamenti', ['idappartamenti' => $codigo, 'maxoccupanti' => $capacidad, 'priorita' => $prioridad]);
    }

    /**
     * @param  array<string, string|int|null>  $extra
     */
    public function cliente(int $id, string $apellido, ?string $nombre = null, array $extra = []): self
    {
        return $this->fila('clienti', $extra + [
            'idclienti' => $id, 'cognome' => $apellido, 'nome' => $nombre,
            'datainserimento' => '2026-01-01 10:00:00',
        ]);
    }

    /**
     * @param  array<string, string|int|null>  $extra
     */
    public function reserva(int $anio, int $id, string $unidad, int $cliente, string $llegada, string $salida, array $extra = [], bool $cancelada = false): self
    {
        return $this->fila(($cancelada ? 'prenotacanc' : 'prenota').$anio, $extra + [
            'idprenota' => $id, 'idclienti' => $cliente, 'idappartamenti' => $unidad,
            'iddatainizio' => $this->idPeriodo($anio, $llegada), 'iddatafine' => $this->idPeriodo($anio, $salida) - 1,
            'num_persone' => 2, 'tariffa_tot' => '300.00', 'pagato' => '100', 'conferma' => 'S',
            'origine' => 'Booking', 'datainserimento' => '2026-02-01 09:30:00',
        ]);
    }

    /**
     * @param  array<string, string|int|null>  $fila
     */
    public function fila(string $tabla, array $fila): self
    {
        $this->filas[$tabla][] = $fila;

        return $this;
    }

    public function texto(): string
    {
        $t = "<?php exit(); ?>\n\n<!--             2026-11-03 10:15:00             -->\n\n"
            ."<!--  **    SAVE THIS FILE AS hoteld_backup.php     **  -->\n\n"
            ."<!--  **  SALVA QUESTO FILE COME hoteld_backup.php  **  -->\n\n\n"
            ."<backup>\n<versione>{$this->version}</versione>\n<log>NO</log>\n";
        $t .= "<file>\n<nomefile>./dati/lingua.php</nomefile>\n<contenuto>\n<?php\n\$lingua[1] = \"es\";\n\$lingua[2] = \"es\";\n?></contenuto>\n</file>\n";
        if ($this->inicioSesion) {
            $t .= "<file>\n<nomefile>./dati/abilita_login</nomefile>\n<contenuto>\n</contenuto>\n</file>\n";
        }
        $t .= "<database>\n";

        $anni = array_map(fn (int $a): array => ['idanni' => $a, 'tipo_periodi' => 'g'], $this->anios);
        $t .= $this->tabla('anni', self::COLUMNAS['anni'], $anni);
        foreach (array_diff(self::GLOBALES, ['anni']) as $tabla) {
            $t .= $this->tabla($tabla, self::COLUMNAS[$tabla], $this->filas[$tabla] ?? []);
        }
        foreach ($this->anios as $anio) {
            foreach (self::ANUALES as $base) {
                $columnas = self::COLUMNAS[$base === 'prenotacanc' ? 'prenota' : $base];
                $filas = $base === 'periodi' ? $this->periodos($anio) : ($this->filas[$base.$anio] ?? []);
                $t .= $this->tabla($base.$anio, $columnas, $filas);
            }
        }

        if ($this->cortada) {
            return $t;
        }

        return $t."</database>\n</backup>";
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
     * Id del periodo diario de una fecha: el 1 es el 1 de enero del año.
     */
    public function idPeriodo(int $anio, string $fecha): int
    {
        return (int) CarbonImmutable::create($anio, 1, 1)->diffInDays(CarbonImmutable::parse($fecha)) + 1;
    }

    /**
     * @param  list<string>  $columnas
     * @param  list<array<string, string|int|null>>  $filas
     */
    private function tabla(string $nombre, array $columnas, array $filas): string
    {
        if (in_array($nombre, $this->tablasOmitidas, true)) {
            return '';
        }

        $t = "<tabella>\n<nometabella>$nombre</nometabella>\n<colonnetabella>\n";
        foreach ($columnas as $columna) {
            $t .= "<nomecolonna>$columna</nomecolonna>\n<tipocolonna>unknown</tipocolonna>\n";
        }
        $t .= "</colonnetabella>\n<righetabella>\n";
        foreach ($filas as $fila) {
            $riga = '';
            foreach ($columnas as $columna) {
                $valor = (string) ($fila[$columna] ?? '');
                $valor = str_replace('@%&@', '@%&@@%&@', $valor);
                $valor = str_replace('</cmp>', '</cmp@%&@>', $valor);
                $riga .= '<cmp>'.$valor.'</cmp>';
            }
            $riga = str_replace('</righetabella>', '</righetabella@%&@>', $riga);
            $riga = str_replace('</riga>', '</riga@%&@>', $riga);
            $riga = str_replace('<riga>', '<riga@%&@>', $riga);
            $t .= "<riga>$riga</riga>\n";
        }

        return $t."</righetabella>\n</tabella>\n";
    }

    /**
     * @return list<array<string, string|int|null>>
     */
    private function periodos(int $anio): array
    {
        $filas = [];
        $dia = CarbonImmutable::create($anio, 1, 1);
        for ($id = 1; $dia->year === $anio; $id++, $dia = $dia->addDay()) {
            $filas[] = ['idperiodi' => $id, 'datainizio' => $dia->format('Y-m-d'), 'datafine' => $dia->addDay()->format('Y-m-d'), 'tariffa1' => '1000'];
        }

        return $filas;
    }
}
