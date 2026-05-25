<?php
/**
 * Example: Transient Stability Analysis — IEEE 9-Bus System
 *
 * This example demonstrates how to run a time-domain Transient Stability
 * Analysis using the IEEE 9-bus test system with a three-phase fault event.
 * The data corresponds to Appendix A.4 of:
 *
 *   SENA, J. A. D. S. Desenvolvimento de Framework para Análise e
 *   Simulação Dinâmica de Sistemas Elétricos de Potência.
 *   Doctoral Thesis — UFU, 2016.
 *   https://repositorio.ufu.br/handle/123456789/18396
 *
 * Scenario:
 *   A three-phase fault is applied at bus 7 (branch 2-7) at t = 0.2 s
 *   and cleared at t = 0.3 s. The simulation runs for 3 seconds.
 *
 * Usage:
 *   php example_stability_9bus.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use NDSE\Tools\TransientAnalysis;

// -------------------------------------------------------------------------
// Input data — IEEE 9-Bus System with dynamic models
//
// Bus array columns:
//   [id, type, Pg(MW), Qg(MVAr), Pd(MW), Qd(MVAr), Gs, Vm(pu), Va(deg), Qmax, Qmin]
//
// Gen array columns (classical and 4th-order models):
//   [bus, model, H(s), D, Xd(pu), Xd'(pu), Xq(pu), Xq'(pu), Td0'(s), Tq0'(s)]
//   model 1 = Gen1 (classical 2nd-order)
//   model 2 = Gen2 (4th-order)
//
// Exc array columns (IEEE Type I exciter):
//   [bus, model, Ka, Ta, Vmin, Vmax, Ke, Te, Kf, Tf, E1, Se1]
//   model 1 = Exc1 (IEEE Type I)
//
// Event array columns:
//   [type, fromBus, toBus, time(s), impedance]
//   type 1 = three-phase fault (branch impedance change)
//   impedance = 1e-10 means bolted fault; 0 means fault cleared (branch restored)
//
// optTA columns:
//   [tmax(s), t0(s), method, dt(s)]
// -------------------------------------------------------------------------

$data = [
    'optLF' => [100, 10, 1e-3, 1],  // [sbase(MVA), max_iter, tolerance, qlim]
    'optTA' => [60, 0, 1, 1e-2],    // [fbase(Hz), t0(s), method, dt(s)]

    'bus' => [
        //  id  type   Pg    Qg   Pd   Qd  Gs    Vm     Va    Qmax  Qmin
        [1,  3,    0,    0,   0,   0,  0, 1.040,  0.300, -300],
        [2,  2,  163,    0,   0,   0,  0, 1.025,  0.300, -300],
        [3,  2,   85,    0,   0,   0,  0, 1.025,  0.300, -300],
        [4,  1,    0,    0,   0,   0,  0, 1.000,  0,        0],
        [5,  1,    0,    0,  90,  30,  0, 1.000,  0,        0],
        [6,  1,    0,    0,   0,   0,  0, 1.000,  0,        0],
        [7,  1,    0,    0, 100,  35,  0, 1.000,  0,        0],
        [8,  1,    0,    0,   0,   0,  0, 1.000,  0,        0],
        [9,  1,    0,    0, 125,  50,  0, 1.000,  0,        0],
    ],

    'branch' => [
        //  from  to      R       X       B    tap  shift  status
        [1,  4,  0.0000, 0.0576, 0.0000,  1,   0,   1],
        [4,  5,  0.0170, 0.0920, 0.1580,  0,   0,   1],
        [5,  6,  0.0390, 0.1700, 0.3580,  0,   0,   1],
        [3,  6,  0.0000, 0.0586, 0.0000,  1,   0,   1],
        [6,  7,  0.0119, 0.1008, 0.2090,  0,   0,   1],
        [7,  8,  0.0085, 0.0720, 0.1490,  0,   0,   1],
        [8,  2,  0.0000, 0.0625, 0.0000,  1,   0,   1],
        [8,  9,  0.0320, 0.1610, 0.3060,  0,   0,   1],
        [9,  4,  0.0100, 0.0850, 0.1760,  0,   0,   1],
    ],

    // Generator dynamic models
    // Gen1 (classical): [bus, model=1, H, D, Xd, Xd', Xq, Xq', Td0', Tq0']
    // Gen2 (4th-order): [bus, model=2, H, D, Xd, Xd', Xq, Xq', Td0', Tq0']
    'gen' => [
        [1, 1, 1.20, 0.02, 0.14, 0.14, 0.00, 0.00, 0.0, 0.0],  // Gen1 at bus 1
        [2, 1, 2.40, 0.01, 0.14, 0.14, 0.00, 0.00, 0.0, 0.0],  // Gen1 at bus 2
        [3, 2, 5.74, 0.02, 1.93, 1.77, 0.25, 0.25, 5.2, 0.81], // Gen2 at bus 3
    ],

    // IEEE Type I Exciter model
    // Exc1: [bus, model=1, Ka, Ta, Vmin, Vmax, Ke, Te, Kf, Tf, E1, Se1]
    'exc' => [
        [3, 1, 50, 0.05, -0.17, 0.95, 0.04, 1.0, 0.014, 1.55, -1.7, 1.7],
    ],

    // Governor models (none in this example)
    'gov' => [],

    // Fault events
    // event: [type, fromBus, toBus, time(s), impedance]
    //   type 1 = three-phase fault applied to branch fromBus-toBus
    //   impedance = 1e-10 → bolted fault (near zero impedance)
    //   impedance = 0     → fault cleared (branch restored to normal)
    'event' => [
        [1, 2, 7, 0.2, 1e-10], // Fault applied  at t = 0.2 s on branch 2-7
        [1, 2, 7, 0.3, 0    ], // Fault cleared  at t = 0.3 s (branch restored)
    ],
];

// -------------------------------------------------------------------------
// Run Transient Stability Analysis
// -------------------------------------------------------------------------

echo "Running Transient Stability Analysis — IEEE 9-Bus System...\n";
echo "  Fault: three-phase fault on branch 2-7\n";
echo "  Applied at t = 0.2 s, cleared at t = 0.3 s\n";
echo "  Simulation duration: 3 s (controlled by optTA[0] / fbase)\n\n";

$ta = new TransientAnalysis($data);
$result = $ta->run();

// -------------------------------------------------------------------------
// Display results
// -------------------------------------------------------------------------

if (empty($result)) {
    echo "Transient stability analysis failed or did not produce results.\n";
    exit(1);
}

$output = json_decode($result, true);

echo "=============================================================\n";
echo "  Transient Stability Results — IEEE 9-Bus System\n";
echo "=============================================================\n";

// The result contains time-series data for each generator rotor angle
// and angular speed. Print a summary of the first and last time steps.

if (isset($output['time']) && isset($output['delta'])) {
    $times  = $output['time'];
    $deltas = $output['delta']; // rotor angles [time][generator]

    $nSteps = count($times);
    $nGen   = count($deltas[0]);

    echo sprintf("  Time steps: %d   |   Generators: %d\n", $nSteps, $nGen);
    echo "-------------------------------------------------------------\n";
    echo sprintf("  %-8s", 'Time(s)');
    for ($g = 0; $g < $nGen; $g++) {
        echo sprintf("  %-14s", "Gen" . ($g + 1) . " δ(°)");
    }
    echo "\n";
    echo "-------------------------------------------------------------\n";

    // Print every 10th time step to keep output manageable
    foreach ($times as $k => $t) {
        if ($k % 10 !== 0 && $k !== $nSteps - 1) {
            continue;
        }
        echo sprintf("  %-8.3f", $t);
        foreach ($deltas[$k] as $delta) {
            echo sprintf("  %-14.4f", $delta * 180 / M_PI); // rad → degrees
        }
        echo "\n";
    }
} else {
    // Fallback: print raw JSON summary
    echo "  Raw result keys: " . implode(', ', array_keys($output)) . "\n";
    echo json_encode($output, JSON_PRETTY_PRINT) . "\n";
}

echo "=============================================================\n";
