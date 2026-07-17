<?php

namespace App\Http\Controllers;

use App\Exports\ReporteConsultaExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReporteSectorController extends Controller
{
    public function index()
    {
        $sectores = DB::table('catastro.tf_sectores')
            ->select('id_sector', 'codi_sector')
            ->orderBy('codi_sector', 'asc')
            ->get();

        return view(
            'pages.reporte.exportarsector',
            compact('sectores')
        );
    }

    public function puertas(Request $request)
    {
        $request->validate([
            'sector' => 'required|max:10',
        ]);

        $sector = $request->sector;

        $sql = "
            WITH puertas AS (
                SELECT 
                    i.id_ficha,
                    v.codi_via,
                    v.tipo_via,
                    v.nomb_via,
                    p.tipo_puerta,
                    p.nume_muni,
                    p.cond_nume,

                    ROW_NUMBER() OVER (
                        PARTITION BY i.id_ficha
                        ORDER BY p.id_puerta
                    ) AS rn

                FROM catastro.tf_ingresos i

                LEFT JOIN catastro.tf_puertas p
                    ON p.id_puerta = i.id_puerta

                LEFT JOIN catastro.tf_vias v
                    ON v.id_via = p.id_via
            )

            SELECT 
                sec.codi_sector,
                mz.codi_mzna,
                l.codi_lote,

                CASE 
                    WHEN p.rn = 1 
                    THEN f.id_uni_cat::text 
                    ELSE '' 
                END AS cod_referencial_catastral,

                CASE 
                    WHEN p.rn = 1 
                    THEN f.nume_ficha::text 
                    ELSE '' 
                END AS nume_ficha,

                p.codi_via,
                p.tipo_via,
                p.nomb_via,
                p.tipo_puerta,
                p.nume_muni,
                p.cond_nume

            FROM catastro.tf_fichas f

            LEFT JOIN puertas p
                ON p.id_ficha = f.id_ficha

            LEFT JOIN catastro.tf_lotes l
                ON l.id_lote = f.id_lote

            LEFT JOIN catastro.tf_manzanas mz
                ON mz.id_mzna = l.id_mzna

            LEFT JOIN catastro.tf_sectores sec
                ON sec.id_sector = mz.id_sector

            WHERE f.tipo_ficha = '01'
            AND sec.codi_sector = ?

            ORDER BY f.nume_ficha, p.rn
        ";

        $datos = DB::select($sql, [$sector]);

        $cabeceras = [
            'Sector',
            'Manzana',
            'Lote',
            'Cod. Referencial Catastral',
            'Número de ficha',
            'Código de vía',
            'Tipo de vía',
            'Nombre de vía',
            'Tipo de puerta',
            'Número municipal',
            'Condición de numeración',
        ];

        return Excel::download(
            new ReporteConsultaExport($datos, $cabeceras),
            'puertas-sector-' . $sector . '.xlsx'
        );
    }

    public function individuales(Request $request)
    {
        $request->validate([
            'sector' => 'required|max:10',
        ]);

        $sector = $request->sector;

        $sql = "
            WITH puertas AS (
                SELECT
                    i.id_ficha,
                    v.codi_via,
                    v.nomb_via,
                    ROW_NUMBER() OVER (
                        PARTITION BY i.id_ficha
                        ORDER BY p.id_puerta
                    ) AS rn
                FROM catastro.tf_ingresos i
                LEFT JOIN catastro.tf_puertas p
                    ON p.id_puerta = i.id_puerta
                LEFT JOIN catastro.tf_vias v
                    ON p.id_via = v.id_via
            ),

            construcciones AS (
                SELECT
                    c.id_ficha,
                    c.nume_piso,
                    c.mep,
                    c.estr_muro_col,
                    c.area_verificada AS area_construccion,
                    ROW_NUMBER() OVER (
                        PARTITION BY c.id_ficha
                        ORDER BY c.id_construccion
                    ) AS rn
                FROM catastro.tf_construcciones c
            ),

            detalle AS (
                SELECT
                    COALESCE(p.id_ficha, c.id_ficha) AS id_ficha,
                    COALESCE(p.rn, c.rn) AS rn,

                    p.codi_via,
                    p.nomb_via,

                    c.nume_piso,
                    c.mep,
                    c.estr_muro_col,
                    c.area_construccion

                FROM puertas p

                FULL JOIN construcciones c
                    ON c.id_ficha = p.id_ficha
                   AND c.rn = p.rn
            )

            SELECT
                CASE
                    WHEN d.rn = 1
                    THEN f.id_uni_cat::text
                    ELSE ''
                END AS cod_referencial_catastral,

                CASE
                    WHEN d.rn = 1
                    THEN f.nume_ficha::text
                    ELSE ''
                END AS nume_ficha,

                d.codi_via,
                d.nomb_via,

                CASE
                    WHEN d.rn = 1
                    THEN h.codi_hab_urba::text
                    ELSE ''
                END AS codi_hab_urba,

                CASE
                    WHEN d.rn = 1
                    THEN h.nomb_hab_urba::text
                    ELSE ''
                END AS nomb_hab_urba,

                CASE
                    WHEN d.rn = 1
                    THEN tf.area_verificada::text
                    ELSE ''
                END AS area_verificada,

                CASE
                    WHEN d.rn = 1
                    THEN li.fren_campo::text
                    ELSE ''
                END AS fren_campo,

                CASE
                    WHEN d.rn = 1
                    THEN li.dere_campo::text
                    ELSE ''
                END AS dere_campo,

                CASE
                    WHEN d.rn = 1
                    THEN li.izqu_campo::text
                    ELSE ''
                END AS izqu_campo,

                CASE
                    WHEN d.rn = 1
                    THEN li.fond_campo::text
                    ELSE ''
                END AS fond_campo,

                d.nume_piso,
                d.mep,
                d.estr_muro_col,
                d.area_construccion,

                CASE
                    WHEN d.rn = 1
                    THEN s.nume_doc::text
                    ELSE ''
                END AS supervisor,

                CASE
                    WHEN d.rn = 1
                    THEN f.fecha_supervision::text
                    ELSE ''
                END AS fecha_supervision,

                CASE
                    WHEN d.rn = 1
                    THEN t.nume_doc::text
                    ELSE ''
                END AS tecnico,

                CASE
                    WHEN d.rn = 1
                    THEN f.fecha_levantamiento::text
                    ELSE ''
                END AS fecha_levantamiento

            FROM catastro.tf_fichas f

            LEFT JOIN detalle d
                ON d.id_ficha = f.id_ficha

            LEFT JOIN catastro.tf_personas t
                ON f.id_tecnico = t.id_persona

            LEFT JOIN catastro.tf_personas s
                ON f.id_supervisor = s.id_persona

            LEFT JOIN catastro.tf_lotes l
                ON l.id_lote = f.id_lote

            LEFT JOIN catastro.tf_manzanas mz
                ON l.id_mzna = mz.id_mzna

            LEFT JOIN catastro.tf_sectores sec
                ON sec.id_sector = mz.id_sector

            LEFT JOIN catastro.tf_hab_urbana h
                ON h.id_hab_urba = l.id_hab_urba

            LEFT JOIN catastro.tf_fichas_individuales tf
                ON tf.id_ficha = f.id_ficha

            LEFT JOIN catastro.tf_linderos li
                ON li.id_ficha = f.id_ficha

            WHERE f.tipo_ficha = '01'
              AND sec.codi_sector = ?

            ORDER BY f.nume_ficha, d.rn
        ";

        $datos = DB::select($sql, [$sector]);

        $cabeceras = [
            'Cod. Referencial Catastral',
            'Número de ficha',
            'Código de vía',
            'Nombre de vía',
            'Código habilitación urbana',
            'Nombre habilitación urbana',
            'Área verificada',
            'Frente',
            'Derecha',
            'Izquierda',
            'Fondo',
            'Número de piso',
            'MEP',
            'Estado muro columna',
            'Área de construcción',
            'Supervisor',
            'Fecha de supervisión',
            'Técnico',
            'Fecha de levantamiento',
        ];

        return Excel::download(
            new ReporteConsultaExport($datos, $cabeceras),
            'individuales-sector-' . $sector . '.xlsx'
        );
    }

    public function economicas(Request $request)
    {
        $request->validate([
            'sector' => 'required|max:10',
        ]);

        $sector = $request->sector;

        $sql = "
            SELECT 
                f.id_uni_cat AS cod_referencial_catastral,
                f.nume_ficha,

                SUM(
                    COALESCE(tf.viap_area_verif, 0) +
                    COALESCE(tf.bc_area_verif, 0) +
                    COALESCE(tf.pred_area_verif, 0)
                ) AS total,

                s.nume_doc AS supervisor,
                f.fecha_supervision,

                t.nume_doc AS tecnico,
                f.fecha_levantamiento

            FROM catastro.tf_fichas AS f

            LEFT JOIN catastro.tf_lotes AS l 
                ON l.id_lote = f.id_lote

            LEFT JOIN catastro.tf_manzanas AS mz 
                ON l.id_mzna = mz.id_mzna

            LEFT JOIN catastro.tf_sectores AS sec 
                ON sec.id_sector = mz.id_sector

            LEFT JOIN catastro.tf_fichas_economicas AS tf 
                ON tf.id_ficha = f.id_ficha

            LEFT JOIN catastro.tf_personas AS s 
                ON f.id_supervisor = s.id_persona

            LEFT JOIN catastro.tf_personas AS t 
                ON f.id_tecnico = t.id_persona

            WHERE f.tipo_ficha = '03'
            AND sec.codi_sector = ?

            GROUP BY 
                f.id_uni_cat,
                f.nume_ficha,
                s.nume_doc,
                f.fecha_supervision,
                t.nume_doc,
                f.fecha_levantamiento

            ORDER BY f.nume_ficha
        ";

        $datos = DB::select($sql, [$sector]);

        $cabeceras = [
            'Cod. Referencial Catastral',
            'Número de ficha',
            'Total',
            'Supervisor',
            'Fecha de supervisión',
            'Técnico',
            'Fecha de levantamiento',
        ];

        return Excel::download(
            new ReporteConsultaExport($datos, $cabeceras),
            'economicas-sector-' . $sector . '.xlsx'
        );
    }

    public function bienComun(Request $request)
    {
        $request->validate([
            'sector' => 'required|max:10',
        ]);

        $sector = $request->sector;

        $sql = "
            WITH puertas AS (
                SELECT 
                    i.id_ficha,
                    v.codi_via,
                    v.nomb_via,

                    ROW_NUMBER() OVER (
                        PARTITION BY i.id_ficha 
                        ORDER BY p.id_puerta
                    ) AS rn

                FROM catastro.tf_ingresos i

                LEFT JOIN catastro.tf_puertas p 
                    ON p.id_puerta = i.id_puerta

                LEFT JOIN catastro.tf_vias v 
                    ON p.id_via = v.id_via
            ),

            construcciones AS (
                SELECT 
                    c.id_ficha,
                    c.nume_piso,
                    c.mep,
                    c.estr_muro_col,
                    c.area_verificada AS area_construccion,

                    ROW_NUMBER() OVER (
                        PARTITION BY c.id_ficha 
                        ORDER BY c.id_construccion
                    ) AS rn

                FROM catastro.tf_construcciones c
            ),

            detalle AS (
                SELECT 
                    COALESCE(p.id_ficha, c.id_ficha) AS id_ficha,
                    COALESCE(p.rn, c.rn) AS rn,

                    p.codi_via,
                    p.nomb_via,

                    c.nume_piso,
                    c.mep,
                    c.estr_muro_col,
                    c.area_construccion

                FROM puertas p

                FULL JOIN construcciones c 
                    ON c.id_ficha = p.id_ficha
                AND c.rn = p.rn
            )

            SELECT 
                CASE 
                    WHEN d.rn = 1 
                    THEN f.id_uni_cat::text 
                    ELSE '' 
                END AS cod_referencial_catastral,

                CASE 
                    WHEN d.rn = 1 
                    THEN f.nume_ficha::text 
                    ELSE '' 
                END AS nume_ficha,

                d.codi_via,
                d.nomb_via,

                CASE 
                    WHEN d.rn = 1 
                    THEN h.codi_hab_urba::text 
                    ELSE '' 
                END AS codi_hab_urba,

                CASE 
                    WHEN d.rn = 1 
                    THEN h.nomb_hab_urba::text 
                    ELSE '' 
                END AS nomb_hab_urba,

                CASE 
                    WHEN d.rn = 1 
                    THEN tf.area_verificada::text 
                    ELSE '' 
                END AS area_verificada,

                CASE 
                    WHEN d.rn = 1 
                    THEN li.fren_campo::text 
                    ELSE '' 
                END AS fren_campo,

                CASE 
                    WHEN d.rn = 1 
                    THEN li.dere_campo::text 
                    ELSE '' 
                END AS dere_campo,

                CASE 
                    WHEN d.rn = 1 
                    THEN li.izqu_campo::text 
                    ELSE '' 
                END AS izqu_campo,

                CASE 
                    WHEN d.rn = 1 
                    THEN li.fond_campo::text 
                    ELSE '' 
                END AS fond_campo,

                d.nume_piso,
                d.mep,
                d.estr_muro_col,
                d.area_construccion,

                CASE 
                    WHEN d.rn = 1 
                    THEN s.nume_doc::text 
                    ELSE '' 
                END AS supervisor,

                CASE 
                    WHEN d.rn = 1 
                    THEN f.fecha_supervision::text 
                    ELSE '' 
                END AS fecha_supervision,

                CASE 
                    WHEN d.rn = 1 
                    THEN t.nume_doc::text 
                    ELSE '' 
                END AS tecnico,

                CASE 
                    WHEN d.rn = 1 
                    THEN f.fecha_levantamiento::text 
                    ELSE '' 
                END AS fecha_levantamiento,

                CASE 
                    WHEN d.rn = 1 
                    THEN tf.en_colindante::text 
                    ELSE '' 
                END AS en_colindante,

                CASE 
                    WHEN d.rn = 1 
                    THEN tf.en_jardin_aislamiento::text 
                    ELSE '' 
                END AS en_jardin_aislamiento,

                CASE 
                    WHEN d.rn = 1 
                    THEN tf.en_area_publica::text 
                    ELSE '' 
                END AS en_area_publica,

                CASE 
                    WHEN d.rn = 1 
                    THEN tf.en_area_intangible::text 
                    ELSE '' 
                END AS en_area_intangible

            FROM catastro.tf_fichas f

            LEFT JOIN detalle d 
                ON d.id_ficha = f.id_ficha

            LEFT JOIN catastro.tf_personas t 
                ON f.id_tecnico = t.id_persona

            LEFT JOIN catastro.tf_personas s 
                ON f.id_supervisor = s.id_persona

            LEFT JOIN catastro.tf_lotes l 
                ON l.id_lote = f.id_lote

            LEFT JOIN catastro.tf_manzanas mz 
                ON l.id_mzna = mz.id_mzna

            LEFT JOIN catastro.tf_sectores sec
                ON sec.id_sector = mz.id_sector

            LEFT JOIN catastro.tf_hab_urbana h 
                ON h.id_hab_urba = l.id_hab_urba

            LEFT JOIN catastro.tf_fichas_bienes_comunes tf 
                ON tf.id_ficha = f.id_ficha

            LEFT JOIN catastro.tf_linderos li 
                ON li.id_ficha = f.id_ficha

            WHERE f.tipo_ficha = '04'
            AND sec.codi_sector = ?

            ORDER BY f.nume_ficha, d.rn
        ";

        $datos = DB::select($sql, [$sector]);

        $cabeceras = [
            'Cod. Referencial Catastral',
            'Número de ficha',
            'Código de vía',
            'Nombre de vía',
            'Código habilitación urbana',
            'Nombre habilitación urbana',
            'Área verificada',
            'Frente',
            'Derecha',
            'Izquierda',
            'Fondo',
            'Número de piso',
            'MEP',
            'Estado muro columna',
            'Área de construcción',
            'Supervisor',
            'Fecha de supervisión',
            'Técnico',
            'Fecha de levantamiento',
            'En colindante',
            'En jardín de aislamiento',
            'En área pública',
            'En área intangible',
        ];

        return Excel::download(
            new ReporteConsultaExport($datos, $cabeceras),
            'bien-comun-sector-' . $sector . '.xlsx'
        );
    }
}