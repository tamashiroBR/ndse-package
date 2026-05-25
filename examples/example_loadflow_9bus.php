<?php
/**
 * Example: Load Flow — IEEE 9-Bus System
 *
 * This example demonstrates how to run a Newton-Raphson Load Flow
 * using the IEEE 9-bus test system data from Appendix A.1 of:
 *
 *   SENA, J. A. D. S. Desenvolvimento de Framework para Análise e
 *   Simulação Dinâmica de Sistemas Elétricos de Potência.
 *   Doctoral Thesis — UFU, 2016.
 *   https://repositorio.ufu.br/handle/123456789/18396
 *
 * Reference values (Anderson & Fouad, 1994):
 *   Bus 1 (Slack): V = 1.040 pu
 *   Bus 2 (Gen):   V = 1.025 pu, P = 163 MW
 *   Bus 3 (Gen):   V = 1.025 pu, P = 85 MW
 *   Total load:    315 MW, 115 MVAr
 *
 * Usage:
 *   php example_loadflow_9bus.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use NDSE\Tools\LoadFlow;

// -------------------------------------------------------------------------
// Input data — IEEE 9-Bus System (Anderson & Fouad, 1994)
//
// Bus columns (12 fields, 0-indexed):
//   [id, type, Pg(MW), Qg(MVAr), Pd(MW), Qd(MVAr), Gs(pu), Bs(pu), Vm(pu), Va(deg), Qmax(MVAr), Qmin(MVAr)]
//
// Bus types:
//   1 = PQ (load bus)
//   2 = PV (generator bus)
//   3 = Slack (reference bus)
//
// Branch columns (8 fields, 0-indexed):
//   [from, to, R(pu), X(pu), B(pu), tap, shift(deg), status]
//   tap = 0 means transmission line; tap > 0 means transformer
// -------------------------------------------------------------------------

$data = [
    // [sbase(MVA), max_iter, tolerance, qlim]
    // qlim=0: reactive power limits not enforced (pure Newton-Raphson)
    'optLF' => [100, 10, 1e-3, 0],

    'bus' => [
        //  id  type   Pg    Qg   Pd   Qd  Gs  Bs    Vm    Va  Qmax  Qmin
        [1,  3,    0,    0,   0,   0,  0,  0, 1.040,  0,  300, -300],  // Slack
        [2,  2,  163,    0,   0,   0,  0,  0, 1.025,  0,  300, -300],  // Gen: 163 MW
        [3,  2,   85,    0,   0,   0,  0,  0, 1.025,  0,  300, -300],  // Gen: 85 MW
        [4,  1,    0,    0,   0,   0,  0,  0, 1.000,  0,    0,    0],
        [5,  1,    0,    0,  90,  30,  0,  0, 1.000,  0,    0,    0],  // Load: 90 MW, 30 MVAr
        [6,  1,    0,    0,   0,   0,  0,  0, 1.000,  0,    0,    0],
        [7,  1,    0,    0, 100,  35,  0,  0, 1.000,  0,    0,    0],  // Load: 100 MW, 35 MVAr
        [8,  1,    0,    0,   0,   0,  0,  0, 1.000,  0,    0,    0],
        [9,  1,    0,    0, 125,  50,  0,  0, 1.000,  0,    0,    0],  // Load: 125 MW, 50 MVAr
    ],

    'branch' => [
        //  from  to      R       X       B    tap  shift  status
        [1,  4,  0.0000, 0.0576, 0.0000,  1,   0,   1],  // Transformer
        [4,  5,  0.0170, 0.0920, 0.1580,  0,   0,   1],
        [5,  6,  0.0390, 0.1700, 0.3580,  0,   0,   1],
        [3,  6,  0.0000, 0.0586, 0.0000,  1,   0,   1],  // Transformer
        [6,  7,  0.0119, 0.1008, 0.2090,  0,   0,   1],
        [7,  8,  0.0085, 0.0720, 0.1490,  0,   0,   1],
        [8,  2,  0.0000, 0.0625, 0.0000,  1,   0,   1],  // Transformer
        [8,  9,  0.0320, 0.1610, 0.3060,  0,   0,   1],
        [9,  4,  0.0100, 0.0850, 0.1760,  0,   0,   1],
    ],
];

// -------------------------------------------------------------------------
// Run Load Flow
// -------------------------------------------------------------------------

$lf = new LoadFlow($data);
$lf->makeYbus();
$result = $lf->run();

// -------------------------------------------------------------------------
// Display results
// -------------------------------------------------------------------------

if (empty($result)) {
    echo "Load flow did not converge.\n";
    exit(1);
}

$output = json_decode($result, true);

echo "=============================================================\n";
echo "  Load Flow Results — IEEE 9-Bus System\n";
echo "=============================================================\n";
echo sprintf("  Converged in %d iteration(s)\n", $output['iteration']);
echo sprintf("  Total active losses:   %.4f MW\n",   $output['loss'][0]);
echo sprintf("  Total reactive losses: %.4f MVAr\n", $output['loss'][1]);
echo "-------------------------------------------------------------\n";
echo sprintf("  %-4s  %-10s  %-10s  %-10s  %-10s\n",
    'Bus', 'V (pu)', 'Angle (°)', 'P (MW)', 'Q (MVAr)');
echo "-------------------------------------------------------------\n";

foreach ($output['bus'] as $bus) {
    // bus result: [id, V, angle, P, Q, Pl, Ql, Qmax, Qmin]
    echo sprintf("  %-4d  %-10.4f  %-10.4f  %-10.4f  %-10.4f\n",
        $bus[0], $bus[1], $bus[2], $bus[3], $bus[4]);
}

echo "-------------------------------------------------------------\n";
echo "\n  Branch Power Flows:\n";
echo sprintf("  %-8s  %-8s  %-10s  %-10s  %-10s  %-10s\n",
    'From', 'To', 'P_km(MW)', 'Q_km(MVAr)', 'P_mk(MW)', 'Q_mk(MVAr)');
echo "-------------------------------------------------------------\n";

foreach ($output['branch'] as $br) {
    // branch result: [from, to, Pkm, Qkm, Pmk, Qmk, Ploss, Qloss]
    echo sprintf("  %-8d  %-8d  %-10.4f  %-10.4f  %-10.4f  %-10.4f\n",
        $br[0], $br[1], $br[2], $br[3], $br[4], $br[5]);
}

echo "=============================================================\n";
