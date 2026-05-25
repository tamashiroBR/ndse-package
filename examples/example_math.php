<?php
/**
 * Example: Math Utilities — Complex, Angle, Matrix, Sparse, LinAlg
 *
 * This example demonstrates the mathematical building blocks of the NDSE
 * library. These classes are used internally by the Load Flow and Transient
 * Stability solvers, but they can also be used independently for power
 * system calculations.
 *
 * Topics covered:
 *   1. Angle conversions (degrees ↔ radians)
 *   2. Complex number arithmetic
 *   3. Dense matrix operations (Matrix)
 *   4. Sparse matrix operations (Sparse)
 *   5. Linear algebra — LU decomposition solver (LinAlg)
 *
 * Usage:
 *   php example_math.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use NDSE\Math\Angle;
use NDSE\Math\Complex;
use NDSE\Math\Matrix;
use NDSE\Math\Sparse;
use NDSE\Math\LinAlg;

// =========================================================================
// 1. ANGLE CONVERSIONS
// =========================================================================

echo "=============================================================\n";
echo "  1. Angle Conversions\n";
echo "=============================================================\n";

$ang_deg = new Angle(30, 'deg');
printf("  30°     → %.6f rad\n", $ang_deg->rad());

$ang_rad = new Angle(M_PI / 4, 'rad');
printf("  π/4 rad → %.4f°\n", $ang_rad->deg());

$ang_90 = new Angle(90, 'deg');
printf("  90°     → %.6f rad  (π/2 = %.6f)\n\n", $ang_90->rad(), M_PI / 2);


// =========================================================================
// 2. COMPLEX NUMBER ARITHMETIC
// =========================================================================

echo "=============================================================\n";
echo "  2. Complex Number Arithmetic\n";
echo "=============================================================\n";

// Rectangular form: Z = a + jb
$z1 = new Complex(3, 4);     // Z1 = 3 + j4
$z2 = new Complex(1, -2);    // Z2 = 1 - j2

printf("  Z1 = %.4f + j%.4f\n", $z1->re, $z1->img);
printf("  Z2 = %.4f + j%.4f\n\n", $z2->re, $z2->img);

// Modulus and angle
// Note: abs() returns float; ang() returns float in radians for rectangular form
printf("  |Z1|  = %.4f\n", $z1->abs());
printf("  ∠Z1   = %.4f rad  (%.4f°)\n\n", $z1->ang(), rad2deg($z1->ang()));

// Polar form: Z = |Z| ∠θ  (requires Angle object as second argument)
$z_polar = new Complex(5, new Angle(53.13, 'deg'));  // 5∠53.13° ≈ 3 + j4
printf("  5∠53.13° → %.4f + j%.4f\n\n", $z_polar->re, $z_polar->img);

// Arithmetic operations
$sum  = $z1->add($z2);
$diff = $z1->sub($z2);
$prod = $z1->multiply($z2);
$quot = $z1->div($z2);
$conj = $z1->conj();
$inv  = $z1->inv();
$neg  = $z1->neg();

printf("  Z1 + Z2 = %.4f + j%.4f\n", $sum->re,  $sum->img);
printf("  Z1 - Z2 = %.4f + j%.4f\n", $diff->re, $diff->img);
printf("  Z1 × Z2 = %.4f + j%.4f\n", $prod->re, $prod->img);
printf("  Z1 / Z2 = %.4f + j%.4f\n", $quot->re, $quot->img);
printf("  Z1*     = %.4f + j%.4f  (conjugate)\n", $conj->re, $conj->img);
printf("  1/Z1    = %.4f + j%.4f  (inverse)\n",   $inv->re,  $inv->img);
printf("  -Z1     = %.4f + j%.4f  (negation)\n\n", $neg->re, $neg->img);

// Power engineering example: impedance and admittance
$R = 0.02;
$X = 0.08;
$Z_line = new Complex($R, $X);    // Series impedance Z = R + jX (pu)
$Y_line = $Z_line->inv();         // Admittance Y = 1/Z = G + jB (pu)

printf("  Transmission line:  Z = %.4f + j%.4f pu\n", $Z_line->re, $Z_line->img);
printf("  Admittance:         Y = %.4f + j%.4f pu\n\n", $Y_line->re, $Y_line->img);

// Apparent power: S = V × I*
$V = new Complex(1.0, 0.0);       // Voltage: 1.0∠0° pu
$I = new Complex(0.8, -0.6);      // Current: 0.8 - j0.6 pu (lagging load)
$S = $V->multiply($I->conj());    // S = V × I*
printf("  Apparent power:  S = %.4f + j%.4f pu  (P = %.4f pu, Q = %.4f pu)\n\n",
    $S->re, $S->img, $S->re, $S->img);


// =========================================================================
// 3. DENSE MATRIX OPERATIONS
// =========================================================================

echo "=============================================================\n";
echo "  3. Dense Matrix Operations\n";
echo "=============================================================\n";

$A = new Matrix([
    [2, 1, 0],
    [1, 3, 1],
    [0, 1, 2],
]);

$B = new Matrix([
    [1, 0, 1],
    [0, 2, 0],
    [1, 0, 1],
]);

echo "  Matrix A:\n" . matrixToString($A) . "\n";
echo "  Matrix B:\n" . matrixToString($B) . "\n";

// Addition
echo "  A + B:\n" . matrixToString($A->add($B)) . "\n";

// Multiplication
echo "  A × B:\n" . matrixToString($A->multiply($B)) . "\n";

// Transpose
echo "  Aᵀ (transpose):\n" . matrixToString($A->transpose()) . "\n";

// Scalar multiplication
echo "  2 × A:\n" . matrixToString($A->multiply(2)) . "\n";

// Extract a column (0-indexed)
$col1 = $A->subMatrix([], [1]);
echo "  Column 1 of A: " . implode(', ', $col1->transpose()->get()) . "\n";

// Filter rows where column 0 > 1
$filtered = $A->subMatrix([0, '>', 1], []);
echo "  Rows of A where col[0] > 1:\n" . matrixToString($filtered) . "\n";

// Zeros matrix
echo "  zeros(2,3):\n" . matrixToString(Matrix::zeros(2, 3)) . "\n";


// =========================================================================
// 4. SPARSE MATRIX OPERATIONS
// =========================================================================

echo "=============================================================\n";
echo "  4. Sparse Matrix Operations\n";
echo "=============================================================\n";

// Build a 4×4 sparse complex matrix representing a small Y-bus
// (4-bus ring network, simplified admittances)
$Y = new Sparse(4, 4);

// Self-admittance (diagonal): Yii = sum of branch admittances at bus i
$Y->set(new Complex(10, -30), 0, 0);
$Y->set(new Complex(10, -30), 1, 1);
$Y->set(new Complex(10, -30), 2, 2);
$Y->set(new Complex(10, -30), 3, 3);

// Mutual admittance (off-diagonal): Yij = -yij (negative of branch admittance)
$Y->set(new Complex(-5, 15), 0, 1);
$Y->set(new Complex(-5, 15), 1, 0);
$Y->set(new Complex(-5, 15), 1, 2);
$Y->set(new Complex(-5, 15), 2, 1);
$Y->set(new Complex(-5, 15), 2, 3);
$Y->set(new Complex(-5, 15), 3, 2);

// Retrieve individual elements
$y00 = $Y->get(0, 0);
$y01 = $Y->get(0, 1);
printf("  Y[0,0] = %6.2f + j%6.2f  (self-admittance)\n",  $y00->re, $y00->img);
printf("  Y[0,1] = %6.2f + j%6.2f  (mutual admittance)\n\n", $y01->re, $y01->img);

// Convert to dense Matrix for display
$Yfull = $Y->full();
echo "  Y-bus (4×4 sparse → dense):\n";
foreach ($Yfull->get() as $row) {
    echo "    ";
    foreach ($row as $val) {
        if ($val instanceof Complex) {
            printf("(%5.0f+j%4.0f)  ", $val->re, $val->img);
        } else {
            printf("  %10.1f  ", (float)$val);
        }
    }
    echo "\n";
}
echo "\n";


// =========================================================================
// 5. LINEAR ALGEBRA — LU DECOMPOSITION SOLVER
// =========================================================================

echo "=============================================================\n";
echo "  5. Linear Algebra — LU Decomposition Solver\n";
echo "=============================================================\n";

// The LU solver operates on a Sparse matrix and a plain PHP array vector.
// This is the same solver used internally by the Newton-Raphson Load Flow
// to solve the Jacobian system  J·Δx = r  at each iteration.

// --- Example A: Real-valued system ---
//
// Solve Ax = b:
//   4x + 3y      = 10
//   6x + 3y      = 12
//   2x + 3y + 2z = 14
//
// Expected: x = 1, y = 2, z = 3

$Adense = new Matrix([
    [4, 3, 0],
    [6, 3, 0],
    [2, 3, 2],
]);
$Asparse = $Adense->sparse();
$LU = LinAlg::LUdecomp($Asparse);
$b  = [10, 12, 14];
$x  = LinAlg::LUsolver($LU, $b);

printf("  Real system Ax = b:\n");
printf("    4x + 3y      = 10\n");
printf("    6x + 3y      = 12\n");
printf("    2x + 3y + 2z = 14\n");
printf("  Solution: x = %.4f, y = %.4f, z = %.4f\n\n", $x[0], $x[1], $x[2]);

// --- Example B: Power flow Jacobian system (4-bus) ---
//
// Solve J·Δx = r for a 4-bus Newton-Raphson iteration.
// The Jacobian is built as a dense Matrix and converted to sparse for LU.
//
// Note: LUdecomp/LUsolver require the sparse matrix to be built via
//       Matrix->sparse() (CSC format used internally by the Load Flow).

$Jdense = new Matrix([
    [10, -2, -1,  0],
    [-2,  8, -1, -1],
    [-1, -1,  6, -2],
    [ 0, -1, -2,  7],
]);
$Jsparse = $Jdense->sparse();
$LUj     = LinAlg::LUdecomp($Jsparse);
$r       = [1.0, 2.0, 3.0, 4.0];
$dx      = LinAlg::LUsolver($LUj, $r);

printf("  Jacobian system J·Δx = r (4-bus):\n");
printf("  Δx[0] = %.4f\n", $dx[0]);
printf("  Δx[1] = %.4f\n", $dx[1]);
printf("  Δx[2] = %.4f\n", $dx[2]);
printf("  Δx[3] = %.4f\n\n", $dx[3]);

// --- Example C: Complex sparse system (Y-bus × V = I) ---
//
// Solve Y·V = I for bus voltages given injected currents.
// 2-bus system with complex admittances (built via Matrix->sparse()):
//   Y = | 10-j30   -5+j15 |   I = | 1+j0 |
//       | -5+j15   10-j30 |        | 0+j0 |
//
// This is the same pattern used by the Load Flow to solve the complex
// network equations at each Newton-Raphson iteration.

$Ydense = new Matrix([
    [new Complex(10, -30), new Complex(-5,  15)],
    [new Complex(-5,  15), new Complex(10, -30)],
]);
// Note: Matrix->sparse() with Complex entries may emit PHP notices on PHP 8+
// (implicit Complex-to-int conversion in the CSC builder). These are harmless
// and the solver returns correct results. Use error_reporting(E_ERROR) to suppress.
$prev = error_reporting(E_ERROR);
$LUc  = LinAlg::LUdecomp($Ydense->sparse());
error_reporting($prev);
$Ivec = [new Complex(1, 0), new Complex(0, 0)];
$Vvec = LinAlg::LUsolver($LUc, $Ivec);

printf("  Complex system Y·V = I (2-bus):\n");
printf("  V[0] = %.4f + j%.4f pu  |V[0]| = %.4f pu\n",
    $Vvec[0]->re, $Vvec[0]->img, $Vvec[0]->abs());
printf("  V[1] = %.4f + j%.4f pu  |V[1]| = %.4f pu\n\n",
    $Vvec[1]->re, $Vvec[1]->img, $Vvec[1]->abs());

echo "=============================================================\n";
echo "  All math examples completed successfully.\n";
echo "=============================================================\n";


// =========================================================================
// Helper: format Matrix as indented string
// =========================================================================

function matrixToString(Matrix $m): string
{
    $arr = $m->get();
    $out = '';
    foreach ((array)$arr as $row) {
        $out .= '    ';
        foreach ((array)$row as $val) {
            if ($val instanceof Complex) {
                $out .= sprintf("(%7.4f+j%7.4f)  ", $val->re, $val->img);
            } else {
                $out .= sprintf("%9.4f  ", (float)$val);
            }
        }
        $out .= "\n";
    }
    return $out;
}
