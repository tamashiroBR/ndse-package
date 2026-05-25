# NDSE package

[![Latest Stable Version](https://poser.pugx.org/tamashiro/ndse/v/stable)](https://packagist.org/packages/tamashiro/ndse)
[![License](https://poser.pugx.org/tamashiro/ndse/license)](https://packagist.org/packages/tamashiro/ndse)
[![PHP Version Require](https://poser.pugx.org/tamashiro/ndse/require/php)](https://packagist.org/packages/tamashiro/ndse)

**NDSE package** is a PHP library for electrical power system simulation. It provides a steady-state **Load Flow** solver and a time-domain **Transient Stability Analysis** engine, both built on top of native PHP sparse matrix and complex number math utilities.

This software is the result of a doctoral thesis defended at the Federal University of Uberlândia, Faculty of Electrical Engineering. The full thesis document is available at the university repository: https://repositorio.ufu.br/handle/123456789/18396.

---

## Requirements

- PHP >= 7.4

## Installation

Install the package via [Composer](https://getcomposer.org):

```bash
composer require tamashiro/ndse
```

---

## Features

| Feature | Description |
|---|---|
| **Load Flow** | Newton-Raphson steady-state power flow solver with reactive power limit enforcement |
| **Transient Analysis** | Implicit trapezoidal time-domain integration with Newton-Raphson corrector |
| **Generator Models** | Classical (`Gen1`) and 4th-order (`Gen2`) synchronous machine models |
| **Exciter Models** | IEEE Type I (`Exc1`) and simplified IEEE ST1-style (`Exc2`) excitation systems |
| **Math Utilities** | Dense and sparse matrix operations, complex arithmetic, and linear algebra solvers |

---

## Usage

### Load Flow — IEEE 9-Bus System

The following example runs a Newton-Raphson Load Flow on the IEEE 9-bus test system
(Anderson & Fouad, 1994), which is the smallest standard benchmark for power flow studies.

```php
use NDSE\Tools\LoadFlow;

$data = [
    // [sbase(MVA), max_iter, tolerance, qlim]
    'optLF' => [100, 10, 1e-3, 1],

    // Bus columns: [id, type, Pg(MW), Qg(MVAr), Pd(MW), Qd(MVAr), Gs, Vm(pu), Va(deg), Qmax, Qmin]
    // Bus types: 1=PQ, 2=PV, 3=Slack
    'bus' => [
        [1, 3,   0,  0,   0,  0, 0, 1.040, 0.300, -300],  // Slack bus
        [2, 2, 163,  0,   0,  0, 0, 1.025, 0.300, -300],  // Generator bus
        [3, 2,  85,  0,   0,  0, 0, 1.025, 0.300, -300],  // Generator bus
        [4, 1,   0,  0,   0,  0, 0, 1.000, 0,        0],
        [5, 1,   0,  0,  90, 30, 0, 1.000, 0,        0],  // Load: 90 MW, 30 MVAr
        [6, 1,   0,  0,   0,  0, 0, 1.000, 0,        0],
        [7, 1,   0,  0, 100, 35, 0, 1.000, 0,        0],  // Load: 100 MW, 35 MVAr
        [8, 1,   0,  0,   0,  0, 0, 1.000, 0,        0],
        [9, 1,   0,  0, 125, 50, 0, 1.000, 0,        0],  // Load: 125 MW, 50 MVAr
    ],

    // Branch columns: [from, to, R(pu), X(pu), B(pu), tap, shift(deg), status]
    // tap=0 means transmission line; tap>0 means transformer
    'branch' => [
        [1, 4, 0.0000, 0.0576, 0.0000, 1, 0, 1],  // Transformer 1-4
        [4, 5, 0.0170, 0.0920, 0.1580, 0, 0, 1],
        [5, 6, 0.0390, 0.1700, 0.3580, 0, 0, 1],
        [3, 6, 0.0000, 0.0586, 0.0000, 1, 0, 1],  // Transformer 3-6
        [6, 7, 0.0119, 0.1008, 0.2090, 0, 0, 1],
        [7, 8, 0.0085, 0.0720, 0.1490, 0, 0, 1],
        [8, 2, 0.0000, 0.0625, 0.0000, 1, 0, 1],  // Transformer 8-2
        [8, 9, 0.0320, 0.1610, 0.3060, 0, 0, 1],
        [9, 4, 0.0100, 0.0850, 0.1760, 0, 0, 1],
    ],
];

$lf = new LoadFlow($data);
$lf->makeYbus();          // Build the admittance matrix (Y-bus)
$result = $lf->run();     // Run Newton-Raphson iterations

$output = json_decode($result, true);
// $output['iteration']  → number of iterations to converge
// $output['bus']        → bus results: [id, V(pu), angle(°), P(MW), Q(MVAr), ...]
// $output['branch']     → branch flows: [from, to, Pkm(MW), Qkm(MVAr), Pmk(MW), Qmk(MVAr), ...]
// $output['loss']       → [totalActiveLoss(MW), totalReactiveLoss(MVAr)]
```

For larger systems, the data can be loaded directly from a JSON file:

```php
use NDSE\Tools\LoadFlow;

// Load the bundled IEEE 57-bus or 118-bus example data
$data = json_decode(file_get_contents(__DIR__ . '/examples/loadflow_57bus.json'), true);

$lf = new LoadFlow($data);
$lf->makeYbus();
$result = $lf->run();
```

---

### Transient Stability Analysis — IEEE 9-Bus System

The following example runs a time-domain Transient Stability Analysis on the IEEE 9-bus
system with a three-phase fault event. The fault is applied at bus 7 (branch 2-7) at
t = 0.2 s and cleared at t = 0.3 s. The simulation uses the implicit trapezoidal method
with a Newton-Raphson corrector.

```php
use NDSE\Tools\TransientAnalysis;

$data = [
    // [sbase(MVA), max_iter, tolerance, qlim]
    'optLF' => [100, 10, 1e-3, 1],

    // [fbase(Hz), t0(s), method, dt(s)]
    'optTA' => [60, 0, 1, 1e-2],

    'bus' => [
        [1, 3,   0,  0,   0,  0, 0, 1.040, 0.300, -300],
        [2, 2, 163,  0,   0,  0, 0, 1.025, 0.300, -300],
        [3, 2,  85,  0,   0,  0, 0, 1.025, 0.300, -300],
        [4, 1,   0,  0,   0,  0, 0, 1.000, 0,        0],
        [5, 1,   0,  0,  90, 30, 0, 1.000, 0,        0],
        [6, 1,   0,  0,   0,  0, 0, 1.000, 0,        0],
        [7, 1,   0,  0, 100, 35, 0, 1.000, 0,        0],
        [8, 1,   0,  0,   0,  0, 0, 1.000, 0,        0],
        [9, 1,   0,  0, 125, 50, 0, 1.000, 0,        0],
    ],

    'branch' => [
        [1, 4, 0.0000, 0.0576, 0.0000, 1, 0, 1],
        [4, 5, 0.0170, 0.0920, 0.1580, 0, 0, 1],
        [5, 6, 0.0390, 0.1700, 0.3580, 0, 0, 1],
        [3, 6, 0.0000, 0.0586, 0.0000, 1, 0, 1],
        [6, 7, 0.0119, 0.1008, 0.2090, 0, 0, 1],
        [7, 8, 0.0085, 0.0720, 0.1490, 0, 0, 1],
        [8, 2, 0.0000, 0.0625, 0.0000, 1, 0, 1],
        [8, 9, 0.0320, 0.1610, 0.3060, 0, 0, 1],
        [9, 4, 0.0100, 0.0850, 0.1760, 0, 0, 1],
    ],

    // Generator models
    // Gen1 (classical 2nd-order): [bus, model=1, H(s), D, Xd, Xd', Xq, Xq', Td0', Tq0']
    // Gen2 (4th-order salient):   [bus, model=2, H(s), D, Xd, Xd', Xq, Xq', Td0', Tq0']
    'gen' => [
        [1, 1, 1.20, 0.02, 0.14, 0.14, 0.00, 0.00, 0.00, 0.00],  // Classical at bus 1
        [2, 1, 2.40, 0.01, 0.14, 0.14, 0.00, 0.00, 0.00, 0.00],  // Classical at bus 2
        [3, 2, 5.74, 0.02, 1.93, 1.77, 0.25, 0.25, 5.20, 0.81],  // 4th-order at bus 3
    ],

    // IEEE Type I Exciter: [bus, model=1, Ka, Ta, Vmin, Vmax, Ke, Te, Kf, Tf, E1, Se1]
    'exc' => [
        [3, 1, 50, 0.05, -0.17, 0.95, 0.04, 1.0, 0.014, 1.55, -1.7, 1.7],
    ],

    // Governor models (none in this example)
    'gov' => [],

    // Fault events: [type, fromBus, toBus, time(s), impedance]
    // type=1: three-phase fault; impedance=1e-10 → bolted fault; impedance=0 → cleared
    'event' => [
        [1, 2, 7, 0.2, 1e-10],  // Fault applied  at t = 0.2 s
        [1, 2, 7, 0.3, 0    ],  // Fault cleared  at t = 0.3 s
    ],
];

$ta = new TransientAnalysis($data);
$result = $ta->run();

$output = json_decode($result, true);
// $output['time']   → array of time instants (s)
// $output['delta']  → rotor angles per generator per time step (rad)
// $output['omega']  → angular speeds per generator per time step (rad/s)
```

---

### Math Utilities

The math classes can also be used independently for general power system calculations.

#### Angle Conversions

```php
use NDSE\Math\Angle;

$deg = new Angle(30, 'deg');
echo $deg->rad();   // 0.5236 (radians)

$rad = new Angle(M_PI / 4, 'rad');
echo $rad->deg();   // 45.0 (degrees)
```

#### Complex Number Arithmetic

```php
use NDSE\Math\Angle;
use NDSE\Math\Complex;

// Rectangular form: Z = a + jb
$z1 = new Complex(3, 4);    // Z1 = 3 + j4
$z2 = new Complex(1, -2);   // Z2 = 1 - j2

echo $z1->abs();             // 5.0      (modulus |Z1|)
echo $z1->ang();             // 0.9273   (angle in radians; use rad2deg() to convert)

$sum  = $z1->add($z2);       // 4 + j2
$diff = $z1->sub($z2);       // 2 + j6
$prod = $z1->multiply($z2);  // 11 - j2
$quot = $z1->div($z2);       // -1 + j2
$conj = $z1->conj();         // 3 - j4  (conjugate)
$inv  = $z1->inv();          // 0.12 - j0.16  (inverse 1/Z1)
$neg  = $z1->neg();          // -3 - j4  (negation)

// Polar form: Z = |Z| ∠θ  (pass an Angle object as second argument)
$z_polar = new Complex(5, new Angle(53.13, 'deg'));  // 5∠53.13° ≈ 3 + j4
echo $z_polar->re;   // ≈ 3.0
echo $z_polar->img;  // ≈ 4.0

// Power engineering: transmission line impedance and admittance
$Z = new Complex(0.02, 0.08);   // Z = R + jX (pu)
$Y = $Z->inv();                  // Y = G + jB (pu)

// Apparent power: S = V × I*
$V = new Complex(1.0, 0.0);     // Voltage 1.0∠0° pu
$I = new Complex(0.8, -0.6);    // Current (lagging load)
$S = $V->multiply($I->conj());  // S = P + jQ
echo $S->re;   // 0.8 (active power, pu)
echo $S->img;  // 0.6 (reactive power, pu)
```

#### Dense Matrix Operations

```php
use NDSE\Math\Matrix;

$A = new Matrix([
    [2, 1, 0],
    [1, 3, 1],
    [0, 1, 2],
]);

$B = new Matrix([[1,0,1],[0,2,0],[1,0,1]]);

$C  = $A->add($B);         // A + B
$D  = $A->multiply($B);    // A × B  (matrix product)
$E  = $A->multiply(2);     // 2 × A  (scalar product)
$At = $A->transpose();     // Aᵀ

// Extract column 1 (0-indexed)
$col = $A->subMatrix([], [1]);

// Filter rows where column 0 > 1
$rows = $A->subMatrix([0, '>', 1], []);

// Get a specific element (row 1, col 2)
$val = $A->get(1, 2);   // 1

// Create a zero matrix
$Z = Matrix::zeros(3, 3);
```

#### Sparse Matrix Operations

```php
use NDSE\Math\Sparse;
use NDSE\Math\Complex;

// Build a 4×4 sparse complex Y-bus matrix
$Y = new Sparse(4, 4);

// Set diagonal (self-admittance)
$Y->set(new Complex(10, -30), 0, 0);
$Y->set(new Complex(10, -30), 1, 1);
$Y->set(new Complex(10, -30), 2, 2);
$Y->set(new Complex(10, -30), 3, 3);

// Set off-diagonal (mutual admittance)
$Y->set(new Complex(-5, 15), 0, 1);
$Y->set(new Complex(-5, 15), 1, 0);
$Y->set(new Complex(-5, 15), 1, 2);
$Y->set(new Complex(-5, 15), 2, 1);

// Retrieve a specific element
$y00 = $Y->get(0, 0);   // Complex(10, -30)

// Convert to dense Matrix for display or further operations
$Yfull = $Y->full();
```

#### LU Decomposition Solver

The `LinAlg` class provides the sparse LU decomposition solver used internally by the
Newton-Raphson Load Flow to solve the Jacobian system **J·Δx = r** at each iteration.
The sparse matrix must be built via `Matrix->sparse()` (CSC format).

```php
use NDSE\Math\Matrix;
use NDSE\Math\Complex;
use NDSE\Math\LinAlg;

// --- Real-valued system: Ax = b ---
// 4x + 3y      = 10
// 6x + 3y      = 12
// 2x + 3y + 2z = 14
// Expected: x=1, y=2, z=3

$A = new Matrix([
    [4, 3, 0],
    [6, 3, 0],
    [2, 3, 2],
]);

$LU = LinAlg::LUdecomp($A->sparse());
$x  = LinAlg::LUsolver($LU, [10, 12, 14]);
// $x = [1.0, 2.0, 3.0]

// --- Complex system: Y·V = I (Y-bus network equations) ---
// Y = | 10-j30   -5+j15 |   I = | 1+j0 |
//     | -5+j15   10-j30 |        | 0+j0 |

$Ydense = new Matrix([
    [new Complex(10, -30), new Complex(-5,  15)],
    [new Complex(-5,  15), new Complex(10, -30)],
]);

$LUc  = LinAlg::LUdecomp($Ydense->sparse());
$Ivec = [new Complex(1, 0), new Complex(0, 0)];
$Vvec = LinAlg::LUsolver($LUc, $Ivec);
// $Vvec[0] → Complex bus voltage at node 0
// $Vvec[1] → Complex bus voltage at node 1
```

---

## Example Files

The `examples/` directory contains ready-to-run PHP scripts and JSON data files for all
standard IEEE test systems used in the doctoral thesis:

| Script | System | Type |
|---|---|---|
| `example_math.php` | — | Math utilities (Angle, Complex, Matrix, Sparse, LinAlg) |
| `example_loadflow_9bus.php` | IEEE 9-Bus | Load Flow (inline data) |
| `example_loadflow_57bus.php` | IEEE 57-Bus | Load Flow (from JSON file) |
| `example_loadflow_118bus.php` | IEEE 118-Bus | Load Flow (from JSON file) |
| `example_stability_9bus.php` | IEEE 9-Bus | Transient Stability Analysis |

Run any example from the command line after installing dependencies:

```bash
composer install
php examples/example_math.php
php examples/example_loadflow_9bus.php
php examples/example_loadflow_57bus.php
php examples/example_loadflow_118bus.php
php examples/example_stability_9bus.php
```

---

## Package Structure

```
src/
├── File.php                  # JSON data file reader
├── Math/
│   ├── AbstractMatrix.php
│   ├── Angle.php
│   ├── Complex.php           # Complex number arithmetic
│   ├── LinAlg.php            # Gaussian elimination and LU decomposition
│   ├── Matrix.php            # Dense matrix operations
│   └── Sparse.php            # Sparse matrix (compressed column)
├── Models/
│   ├── AbstractModel.php     # Base class for dynamic equipment models
│   ├── Exc/
│   │   ├── Exc1.php          # IEEE Type I exciter
│   │   └── Exc2.php          # Simplified IEEE ST1 exciter
│   └── Gen/
│       ├── Gen1.php          # Classical synchronous generator
│       └── Gen2.php          # 4th-order synchronous generator
└── Tools/
    ├── AbstractTools.php     # Base class for solver tools
    ├── LoadFlow.php          # Newton-Raphson load flow solver
    └── TransientAnalysis.php # Trapezoidal transient stability solver

examples/
├── README.md                      # Example data format documentation
├── loadflow_9bus.json             # IEEE 9-Bus load flow data
├── loadflow_57bus.json            # IEEE 57-Bus load flow data
├── loadflow_118bus.json           # IEEE 118-Bus load flow data
├── stability_9bus.json            # IEEE 9-Bus transient stability data
├── example_loadflow_9bus.php      # Load flow example (inline data)
├── example_loadflow_57bus.php     # Load flow example (JSON file)
├── example_loadflow_118bus.php    # Load flow example (JSON file)
└── example_stability_9bus.php     # Transient stability example
```

---

## License

This project is licensed under the **GNU General Public License v2.0 or later**. See the [LICENSE](LICENSE) file for details.
