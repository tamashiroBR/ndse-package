<?php
/**
 * Example: Load Flow — IEEE 57-Bus System
 *
 * This example demonstrates how to run a Newton-Raphson Load Flow
 * using the IEEE 57-bus test system data loaded from a JSON file.
 * The data corresponds to Appendix A.2 of:
 *
 *   SENA, J. A. D. S. Desenvolvimento de Framework para Análise e
 *   Simulação Dinâmica de Sistemas Elétricos de Potência.
 *   Doctoral Thesis — UFU, 2016.
 *   https://repositorio.ufu.br/handle/123456789/18396
 *
 * Usage:
 *   php example_loadflow_57bus.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use NDSE\Tools\LoadFlow;

// -------------------------------------------------------------------------
// Load input data from the bundled JSON file
// -------------------------------------------------------------------------

$jsonFile = __DIR__ . '/loadflow_57bus.json';

if (!file_exists($jsonFile)) {
    echo "Error: data file not found at {$jsonFile}\n";
    exit(1);
}

$data = json_decode(file_get_contents($jsonFile), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Error: failed to parse JSON — " . json_last_error_msg() . "\n";
    exit(1);
}

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

$nBus    = count($output['bus']);
$nBranch = count($output['branch']);

echo "=============================================================\n";
echo "  Load Flow Results — IEEE 57-Bus System\n";
echo "=============================================================\n";
echo sprintf("  Buses: %d   |   Branches: %d\n", $nBus, $nBranch);
echo sprintf("  Converged in %d iteration(s)\n", $output['iteration']);
echo sprintf("  Total active losses:   %.4f MW\n",   $output['loss'][0]);
echo sprintf("  Total reactive losses: %.4f MVAr\n", $output['loss'][1]);
echo "-------------------------------------------------------------\n";
echo sprintf("  %-4s  %-10s  %-10s  %-10s  %-10s\n",
    'Bus', 'V (pu)', 'Angle (°)', 'P (MW)', 'Q (MVAr)');
echo "-------------------------------------------------------------\n";

foreach ($output['bus'] as $bus) {
    // bus: [id, V, angle, P, Q, Pl, Ql, Qmax, Qmin]
    echo sprintf("  %-4d  %-10.4f  %-10.4f  %-10.4f  %-10.4f\n",
        $bus[0], $bus[1], $bus[2], $bus[3], $bus[4]);
}

echo "=============================================================\n";
echo sprintf("  Total active losses:   %.4f MW\n",   $output['loss'][0]);
echo sprintf("  Total reactive losses: %.4f MVAr\n", $output['loss'][1]);
echo "=============================================================\n";
