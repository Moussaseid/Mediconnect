<?php
/**
 * scripts/generate_pbit.php — Générateur du dashboard Power BI Template (.pbit)
 *
 * Usage: php scripts/generate_pbit.php
 *
 * Génère: powerbi/MediConnect_Dashboard_P4.pbit
 * Ce fichier s'ouvre directement dans Power BI Desktop 2.155+.
 * Au premier chargement, Power BI charge les données depuis les CSV locaux.
 */

define('ROOT', dirname(__DIR__));

$csvDir  = ROOT . DIRECTORY_SEPARATOR . 'powerbi-data';
$outDir  = ROOT . DIRECTORY_SEPARATOR . 'powerbi';
$outFile = $outDir . DIRECTORY_SEPARATOR . 'MediConnect_Dashboard_P4.pbit';

if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function uuid4(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function j(mixed $v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// Crée une expression M Power Query pour lire un CSV UTF-8 BOM
function mExpr(string $path, array $colTypes): array {
    $path  = str_replace('/', '\\', $path);
    $types = [];
    foreach ($colTypes as $col => $t) {
        $types[] = '{"' . $col . '", ' . $t . '}';
    }
    $n = count($colTypes);
    return [
        'let',
        '    Source = Csv.Document(File.Contents("' . $path . '"),[Delimiter=",", Columns=' . $n . ', Encoding=65001, QuoteStyle=QuoteStyle.None]),',
        '    promoted = Table.PromoteHeaders(Source, [PromoteAllScalars=true]),',
        '    typed = Table.TransformColumnTypes(promoted, {' . implode(', ', $types) . '})',
        'in',
        '    typed',
    ];
}

// ── Définition des tables CSV ─────────────────────────────────────────────────

$tables = [
    'stock_pharmacies' => [
        'file' => $csvDir . DIRECTORY_SEPARATOR . 'stock_pharmacies.csv',
        'cols' => [
            'pharmacie'          => 'type text',
            'nb_medicaments'     => 'Int64.Type',
            'valeur_stock'       => 'type number',
            'alertes_stock'      => 'Int64.Type',
            'alertes_peremption' => 'Int64.Type',
        ],
    ],
    'commandes_mode' => [
        'file' => $csvDir . DIRECTORY_SEPARATOR . 'commandes_stats_mode.csv',
        'cols' => [
            'mode_retrait' => 'type text',
            'nb_commandes' => 'Int64.Type',
        ],
    ],
    'commandes_mensuel' => [
        'file' => $csvDir . DIRECTORY_SEPARATOR . 'commandes_stats_mensuel.csv',
        'cols' => [
            'mois'         => 'type text',
            'mois_label'   => 'type text',
            'mode_retrait' => 'type text',
            'nb_commandes' => 'Int64.Type',
        ],
    ],
    'commandes_statut' => [
        'file' => $csvDir . DIRECTORY_SEPARATOR . 'commandes_stats_statut.csv',
        'cols' => [
            'statut'       => 'type text',
            'nb_commandes' => 'Int64.Type',
        ],
    ],
    'top_medicaments' => [
        'file' => $csvDir . DIRECTORY_SEPARATOR . 'top_medicaments.csv',
        'cols' => [
            'nom_medicament'  => 'type text',
            'quantite_totale' => 'Int64.Type',
            'nb_commandes'    => 'Int64.Type',
        ],
    ],
    'admin_kpis' => [
        'file' => $csvDir . DIRECTORY_SEPARATOR . 'admin_kpis.csv',
        'cols' => [
            'patients'             => 'Int64.Type',
            'medecins'             => 'Int64.Type',
            'medecins_actifs'      => 'Int64.Type',
            'medecins_inactifs'    => 'Int64.Type',
            'pharmacies_actives'   => 'Int64.Type',
            'rdv_ce_mois'          => 'Int64.Type',
            'rdv_mois_dernier'     => 'Int64.Type',
            'rdv_total'            => 'Int64.Type',
            'rdv_annules'          => 'Int64.Type',
            'taux_annulation_pct'  => 'type number',
            'demandes_attente'     => 'Int64.Type',
            'commandes_total'      => 'Int64.Type',
            'commandes_en_attente' => 'Int64.Type',
        ],
    ],
    'utilisateurs_role' => [
        'file' => $csvDir . DIRECTORY_SEPARATOR . 'utilisateurs_par_role.csv',
        'cols' => [
            'role' => 'type text',
            'nb'   => 'Int64.Type',
        ],
    ],
    'mongo_actions' => [
        'file' => $csvDir . DIRECTORY_SEPARATOR . 'mongo_logs_actions.csv',
        'cols' => [
            'action' => 'type text',
            'count'  => 'Int64.Type',
        ],
    ],
    'mongo_daily' => [
        'file' => $csvDir . DIRECTORY_SEPARATOR . 'mongo_logs_daily.csv',
        'cols' => [
            'jour'  => 'type text',
            'count' => 'Int64.Type',
        ],
    ],
];

// ── DataModelSchema (TMSL JSON) ───────────────────────────────────────────────

$tmslTables = [];
foreach ($tables as $tname => $tdef) {
    $cols = [];
    foreach ($tdef['cols'] as $cname => $ctype) {
        $pbType    = match (true) {
            $ctype === 'Int64.Type'  => 'int64',
            $ctype === 'type number' => 'double',
            default                  => 'string',
        };
        $summarize = ($pbType === 'string') ? 'none' : 'sum';
        $cols[]    = [
            'name'        => $cname,
            'dataType'    => $pbType,
            'lineageTag'  => uuid4(),
            'summarizeBy' => $summarize,
            'annotations' => [['name' => 'SummarizationSetBy', 'value' => 'Automatic']],
        ];
    }

    $tmslTables[] = [
        'name'       => $tname,
        'lineageTag' => uuid4(),
        'columns'    => $cols,
        'partitions' => [[
            'name'   => $tname . '-' . uuid4(),
            'mode'   => 'import',
            'source' => [
                'type'       => 'm',
                'expression' => mExpr($tdef['file'], $tdef['cols']),
            ],
        ]],
    ];
}

$dataModelSchema = [
    'name'               => 'SemanticModel',
    'compatibilityLevel' => 1604,
    'model'              => [
        'name'                            => 'Model',
        'defaultPowerBIDataSourceVersion' => 'powerBI_V3',
        'tables'                          => $tmslTables,
        'relationships'                   => [],
        'annotations'                     => [
            ['name' => 'PBI_QueryOrder',                'value' => j(array_keys($tables))],
            ['name' => '__PBI_TimeIntelligenceEnabled',  'value' => '0'],
            ['name' => 'PBIDesktopVersion',             'value' => '2.155.756.0 (24.12)'],
        ],
    ],
];

// ── Visual builder helpers ────────────────────────────────────────────────────

function makeVisual(
    int    $x,
    int    $y,
    int    $w,
    int    $h,
    int    $z,
    string $config,
    string $query,
    string $transforms
): array {
    return [
        'x'              => $x,
        'y'              => $y,
        'z'              => $z,
        'width'          => $w,
        'height'         => $h,
        'config'         => $config,
        'filters'        => '[]',
        'query'          => $query,
        'dataTransforms' => $transforms,
    ];
}

function makeCardConfig(string $name, string $table, string $col, string $title): string {
    $src    = substr($table, 0, 2);
    $valRef = "Sum($table.$col)";
    return j([
        'name'         => $name,
        'layouts'      => [['id' => 0, 'position' => ['x' => 0, 'y' => 0, 'z' => 0, 'width' => 100, 'height' => 100, 'tabOrder' => 0]]],
        'singleVisual' => [
            'visualType'     => 'card',
            'projections'    => ['Values' => [['queryRef' => $valRef]]],
            'prototypeQuery' => [
                'Version' => 2,
                'From'    => [['Name' => $src, 'Entity' => $table, 'Type' => 0]],
                'Select'  => [[
                    'Aggregation' => [
                        'Expression' => ['Column' => ['Expression' => ['SourceRef' => ['Source' => $src]], 'Property' => $col]],
                        'Function'   => 0,
                    ],
                    'Name' => $valRef,
                ]],
            ],
            'objects' => [
                'title' => [[
                    'properties' => [
                        'show' => ['expr' => ['Literal' => ['Value' => 'true']]],
                        'text' => ['expr' => ['Literal' => ['Value' => "'" . $title . "'"]]],
                    ],
                ]],
            ],
        ],
    ]);
}

function makeCardQuery(string $table, string $col): string {
    $src    = substr($table, 0, 2);
    $valRef = "Sum($table.$col)";
    return j([
        'Commands' => [[
            'SemanticQueryDataShapeCommand' => [
                'Query' => [
                    'Version' => 2,
                    'From'    => [['Name' => $src, 'Entity' => $table, 'Type' => 0]],
                    'Select'  => [[
                        'Aggregation' => [
                            'Expression' => ['Column' => ['Expression' => ['SourceRef' => ['Source' => $src]], 'Property' => $col]],
                            'Function'   => 0,
                        ],
                        'Name' => $valRef,
                    ]],
                ],
                'Binding' => [
                    'Primary'            => ['Groupings' => [['Projections' => [0]]]],
                    'DataReduction'      => ['DataVolume' => 4, 'Primary' => ['Window' => ['Count' => 1000]]],
                    'IncludeEmptyGroups' => false,
                ],
            ],
        ]],
    ]);
}

function makeCardTransforms(string $table, string $col): string {
    $valRef = "Sum($table.$col)";
    return j(['selects' => [['displayName' => "Somme de $col", 'queryName' => $valRef, 'type' => ['numeric' => true]]]]);
}

function kpi(int $x, int $y, int $w, int $h, int $z, string $table, string $col, string $title): array {
    return makeVisual($x, $y, $w, $h, $z,
        makeCardConfig("kpi_$col", $table, $col, $title),
        makeCardQuery($table, $col),
        makeCardTransforms($table, $col)
    );
}

function makeChartConfig(
    string  $name,
    string  $type,
    string  $table,
    string  $catCol,
    string  $valCol,
    string  $title,
    array   $extraObjects = [],
    ?string $seriesCol = null
): string {
    $src    = substr($table, 0, 2);
    $catRef = "$table.$catCol";
    $valRef = "Sum($table.$valCol)";

    $projections  = [
        'Category' => [['queryRef' => $catRef]],
        'Y'        => [['queryRef' => $valRef]],
    ];
    $protoSelect  = [
        ['Column' => ['Expression' => ['SourceRef' => ['Source' => $src]], 'Property' => $catCol], 'Name' => $catRef],
        ['Aggregation' => ['Expression' => ['Column' => ['Expression' => ['SourceRef' => ['Source' => $src]], 'Property' => $valCol]], 'Function' => 0], 'Name' => $valRef],
    ];

    if ($seriesCol !== null) {
        $serRef                  = "$table.$seriesCol";
        $projections['Series']   = [['queryRef' => $serRef]];
        $protoSelect[]           = ['Column' => ['Expression' => ['SourceRef' => ['Source' => $src]], 'Property' => $seriesCol], 'Name' => $serRef];
    }

    $objects = array_merge([
        'title' => [[
            'properties' => [
                'show' => ['expr' => ['Literal' => ['Value' => 'true']]],
                'text' => ['expr' => ['Literal' => ['Value' => "'" . $title . "'"]]],
            ],
        ]],
    ], $extraObjects);

    return j([
        'name'         => $name,
        'layouts'      => [['id' => 0, 'position' => ['x' => 0, 'y' => 0, 'z' => 0, 'width' => 100, 'height' => 100, 'tabOrder' => 0]]],
        'singleVisual' => [
            'visualType'     => $type,
            'projections'    => $projections,
            'prototypeQuery' => [
                'Version' => 2,
                'From'    => [['Name' => $src, 'Entity' => $table, 'Type' => 0]],
                'Select'  => $protoSelect,
            ],
            'objects'        => $objects,
        ],
    ]);
}

function makeChartQuery(string $table, string $catCol, string $valCol, ?string $seriesCol = null): string {
    $src      = substr($table, 0, 2);
    $catRef   = "$table.$catCol";
    $valRef   = "Sum($table.$valCol)";
    $select   = [
        ['Column' => ['Expression' => ['SourceRef' => ['Source' => $src]], 'Property' => $catCol], 'Name' => $catRef],
        ['Aggregation' => ['Expression' => ['Column' => ['Expression' => ['SourceRef' => ['Source' => $src]], 'Property' => $valCol]], 'Function' => 0], 'Name' => $valRef],
    ];
    $projections = [0, 1];

    if ($seriesCol !== null) {
        $serRef        = "$table.$seriesCol";
        $select[]      = ['Column' => ['Expression' => ['SourceRef' => ['Source' => $src]], 'Property' => $seriesCol], 'Name' => $serRef];
        $projections   = [0, 1, 2];
    }

    return j([
        'Commands' => [[
            'SemanticQueryDataShapeCommand' => [
                'Query' => ['Version' => 2, 'From' => [['Name' => $src, 'Entity' => $table, 'Type' => 0]], 'Select' => $select],
                'Binding' => [
                    'Primary'            => ['Groupings' => [['Projections' => $projections]]],
                    'DataReduction'      => ['DataVolume' => 4, 'Primary' => ['Window' => ['Count' => 1000]]],
                    'IncludeEmptyGroups' => false,
                ],
            ],
        ]],
    ]);
}

function makeChartTransforms(string $table, string $catCol, string $valCol, ?string $seriesCol = null): string {
    $catRef  = "$table.$catCol";
    $valRef  = "Sum($table.$valCol)";
    $selects = [
        ['displayName' => $catCol,           'queryName' => $catRef, 'type' => ['category' => 'text']],
        ['displayName' => "Somme de $valCol", 'queryName' => $valRef, 'type' => ['numeric' => true]],
    ];
    if ($seriesCol !== null) {
        $selects[] = ['displayName' => $seriesCol, 'queryName' => "$table.$seriesCol", 'type' => ['category' => 'text']];
    }
    return j(['selects' => $selects]);
}

function chart(
    int     $x,
    int     $y,
    int     $w,
    int     $h,
    int     $z,
    string  $name,
    string  $type,
    string  $table,
    string  $catCol,
    string  $valCol,
    string  $title,
    array   $extraObjects = [],
    ?string $seriesCol = null
): array {
    return makeVisual($x, $y, $w, $h, $z,
        makeChartConfig($name, $type, $table, $catCol, $valCol, $title, $extraObjects, $seriesCol),
        makeChartQuery($table, $catCol, $valCol, $seriesCol),
        makeChartTransforms($table, $catCol, $valCol, $seriesCol)
    );
}

$blue = ['dataColors' => [['properties' => ['fill' => ['solid' => ['color' => '#0D6EFD']]]]]];

// ── Page 1 : Stock & Péremptions ──────────────────────────────────────────────

$titleBoxConfig = j([
    'name'         => 'titleBox',
    'layouts'      => [['id' => 0, 'position' => ['x' => 0, 'y' => 0, 'z' => 0, 'width' => 100, 'height' => 100, 'tabOrder' => 0]]],
    'singleVisual' => [
        'visualType' => 'textbox',
        'objects'    => [
            'general' => [[
                'properties' => [
                    'paragraphs' => ['expr' => ['Literal' => ['Value' => '"[{\"textRuns\":[{\"value\":\"MediConnect — Dashboard Pharmacie & Admin\",\"textRunStyle\":{\"fontSize\":14,\"fontFamily\":\"Segoe UI\",\"bold\":true,\"color\":\"#0D6EFD\"}}]}]"']]],
                ],
            ]],
        ],
    ],
]);

$page1Visuals = [
    [
        'x' => 20, 'y' => 10, 'z' => 500, 'width' => 1240, 'height' => 44,
        'config'         => $titleBoxConfig,
        'filters'        => '[]',
        'query'          => '{}',
        'dataTransforms' => '{}',
    ],
    kpi(20,  70, 260, 90, 1000, 'stock_pharmacies', 'valeur_stock',       'Valeur totale stock (EUR)'),
    kpi(300, 70, 220, 90, 1001, 'stock_pharmacies', 'alertes_stock',      'Alertes stock < 30'),
    kpi(540, 70, 240, 90, 1002, 'stock_pharmacies', 'alertes_peremption', 'Peremptions < 6 mois'),

    chart(20,  175, 590, 270, 1010,
        'chartValeurStock', 'columnChart', 'stock_pharmacies', 'pharmacie', 'valeur_stock',
        'Valeur du stock par pharmacie (EUR)', $blue),

    chart(630, 175, 630, 270, 1011,
        'chartAlertes', 'clusteredColumnChart', 'stock_pharmacies', 'pharmacie', 'alertes_stock',
        'Alertes stock et peremptions par pharmacie'),

    chart(20,  460, 1240, 240, 1012,
        'tableStock', 'tableEx', 'stock_pharmacies', 'pharmacie', 'valeur_stock',
        'Detail stock par pharmacie'),
];

// ── Page 2 : Commandes ────────────────────────────────────────────────────────

$page2Visuals = [
    kpi(20, 10, 200, 80, 2000, 'commandes_mode', 'nb_commandes', 'Total commandes'),

    chart(20,  105, 360, 280, 2001,
        'donutMode', 'donutChart', 'commandes_mode', 'mode_retrait', 'nb_commandes',
        'Repartition des modes de retrait'),

    chart(400, 105, 860, 280, 2002,
        'lineEvolution', 'lineChart', 'commandes_mensuel', 'mois', 'nb_commandes',
        'Evolution des commandes sur 6 mois', [], 'mode_retrait'),

    chart(20,  400, 580, 290, 2003,
        'barStatut', 'clusteredBarChart', 'commandes_statut', 'statut', 'nb_commandes',
        'Commandes par statut'),

    chart(620, 400, 640, 290, 2004,
        'barTopMeds', 'clusteredBarChart', 'top_medicaments', 'nom_medicament', 'quantite_totale',
        'Top 10 medicaments les plus commandes'),
];

// ── Page 3 : Admin & Logs ─────────────────────────────────────────────────────

$page3Visuals = [
    kpi(20,  10, 220, 80, 3000, 'admin_kpis', 'taux_annulation_pct', 'Taux annulation RDV (%)'),
    kpi(260, 10, 200, 80, 3001, 'admin_kpis', 'demandes_attente',    'Demandes en attente'),
    kpi(480, 10, 200, 80, 3002, 'admin_kpis', 'medecins_actifs',     'Medecins actifs'),
    kpi(700, 10, 200, 80, 3003, 'admin_kpis', 'rdv_ce_mois',         'RDV ce mois'),

    chart(20,  105, 380, 280, 3004,
        'pieRoles', 'pieChart', 'utilisateurs_role', 'role', 'nb',
        'Repartition des utilisateurs par role'),

    chart(420, 105, 840, 280, 3005,
        'barActions', 'columnChart', 'mongo_actions', 'action', 'count',
        'Actions administrateur par type (30 derniers jours)', $blue),

    chart(20,  400, 1240, 290, 3006,
        'lineDaily', 'lineChart', 'mongo_daily', 'jour', 'count',
        'Activite admin - 30 derniers jours', $blue),
];

// ── Report/Layout JSON ────────────────────────────────────────────────────────

$reportConfig = j([
    'version'               => '5.52',
    'themeCollection'       => ['baseTheme' => ['name' => 'CY24SU08', 'version' => '5.52', 'type' => 2]],
    'activeSectionIndex'    => 0,
    'settings'              => [
        'useStylableVisualContainerHeader' => true,
        'isPaginatedReportMode'            => false,
    ],
    'linguisticSchemaSyncVersion' => 0,
    'filterConfig'          => ['type' => 1],
]);

$reportLayout = [
    'id'               => 0,
    'resourcePackages' => [],
    'sections'         => [
        [
            'id'                   => 0,
            'name'                 => 'ReportSection1',
            'displayName'          => 'Stock et Peremptions',
            'filters'              => '[]',
            'ordinal'              => 0,
            'height'               => 720,
            'width'                => 1280,
            'defaultDisplayOption' => 0,
            'visualContainers'     => $page1Visuals,
        ],
        [
            'id'                   => 1,
            'name'                 => 'ReportSection2',
            'displayName'          => 'Commandes',
            'filters'              => '[]',
            'ordinal'              => 1,
            'height'               => 720,
            'width'                => 1280,
            'defaultDisplayOption' => 0,
            'visualContainers'     => $page2Visuals,
        ],
        [
            'id'                   => 2,
            'name'                 => 'ReportSection3',
            'displayName'          => 'Admin et Logs',
            'filters'              => '[]',
            'ordinal'              => 2,
            'height'               => 720,
            'width'                => 1280,
            'defaultDisplayOption' => 0,
            'visualContainers'     => $page3Visuals,
        ],
    ],
    'config' => $reportConfig,
];

// ── ZIP assembly ──────────────────────────────────────────────────────────────

$metadata = [
    'version'     => '4.0',
    'createdFrom' => 'ReportId',
    'spaceId'     => uuid4(),
    'reportId'    => uuid4(),
    'description' => 'MediConnect Dashboard Pharmacie & Admin — Sprint 3',
    'author'      => 'MediConnect',
];

$settings = [
    'version'                          => '1.0',
    'useStylableVisualContainerHeader' => true,
];

$contentTypes = '<?xml version="1.0" encoding="utf-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.ms-package.relationships+xml"/>
  <Override PartName="/DataModelSchema" ContentType="application/json; charset=utf-8"/>
  <Override PartName="/DiagramLayout" ContentType="application/json; charset=utf-8"/>
  <Override PartName="/Report/Layout" ContentType="application/json; charset=utf-8"/>
  <Override PartName="/Settings" ContentType="application/json; charset=utf-8"/>
  <Override PartName="/Metadata" ContentType="application/json; charset=utf-8"/>
  <Override PartName="/Version" ContentType="text/plain"/>
</Types>';

$rels = '<?xml version="1.0" encoding="utf-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.microsoft.com/powerbi/2018/2/relationships/dataModelSchema" Target="DataModelSchema"/>
  <Relationship Id="rId2" Type="http://schemas.microsoft.com/powerbi/2018/2/relationships/report" Target="Report/Layout"/>
  <Relationship Id="rId3" Type="http://schemas.microsoft.com/powerbi/2018/2/relationships/settings" Target="Settings"/>
  <Relationship Id="rId4" Type="http://schemas.microsoft.com/powerbi/2018/2/relationships/metadata" Target="Metadata"/>
  <Relationship Id="rId5" Type="http://schemas.microsoft.com/powerbi/2018/2/relationships/version" Target="Version"/>
  <Relationship Id="rId6" Type="http://schemas.microsoft.com/powerbi/2018/2/relationships/diagramLayout" Target="DiagramLayout"/>
</Relationships>';

$diagramLayout = j(['version' => '1.1', 'pinKeyFieldsToTop' => false, 'useHierarchies' => true, 'entities' => []]);

echo "Generation du fichier .pbit...\n";

$zip = new ZipArchive();
if ($zip->open($outFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Impossible de creer le fichier ZIP : $outFile\n");
}

$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels',         $rels);
$zip->addFromString('Version',             '2.0');
$zip->addFromString('DataModelSchema',     json_encode($dataModelSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
$zip->addFromString('Report/Layout',       json_encode($reportLayout,   JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$zip->addFromString('Metadata',            json_encode($metadata,       JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
$zip->addFromString('Settings',            json_encode($settings,       JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
$zip->addFromString('DiagramLayout',       $diagramLayout);
$zip->close();

$size = round(filesize($outFile) / 1024, 1);
echo "Fichier genere : $outFile ($size Ko)\n";
echo "   -> Ouvre-le dans Power BI Desktop\n";
echo "   -> Au chargement, clique 'Actualiser' pour charger les donnees CSV\n";
echo "\nContenu du .pbit :\n";

$z = new ZipArchive();
$z->open($outFile);
for ($i = 0; $i < $z->numFiles; $i++) {
    $stat = $z->statIndex($i);
    echo '   ' . $stat['name'] . ' (' . round($stat['size'] / 1024, 1) . " Ko)\n";
}
$z->close();
