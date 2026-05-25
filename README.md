# NDSE — Network Dynamic Simulation Engine

[![Latest Stable Version](https://poser.pugx.org/tamashiro/ndse/v/stable)](https://packagist.org/packages/tamashiro/ndse)
[![License](https://poser.pugx.org/tamashiro/ndse/license)](https://packagist.org/packages/tamashiro/ndse)
[![PHP Version Require](https://poser.pugx.org/tamashiro/ndse/require/php)](https://packagist.org/packages/tamashiro/ndse)

**NDSE** is a PHP library for electrical power system simulation. It provides a steady-state **Load Flow** solver and a time-domain **Transient Stability Analysis** engine, both built on top of native PHP sparse matrix and complex number math utilities.

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

### Load Flow

```php
use NDSE\Tools\LoadFlow;

$data = [
    'optLF'  => [100, 20, 1e-3, 1], // sbase (MVA), max_iter, tol, qlim
    'bus'    => [...],               // bus data array
    'branch' => [...],               // branch data array
];

$lf = new LoadFlow($data);
$lf->makeYbus();
$lf->run();

$results = $lf->getData();
```

### Transient Stability Analysis

```php
use NDSE\Tools\TransientAnalysis;

$data = [
    'optLF'  => [100, 20, 1e-3, 1],
    'optTA'  => [60, 0, 10, 0.01],  // fbase (Hz), tstart, tstop, step (s)
    'bus'    => [...],
    'branch' => [...],
    'gen'    => [...],
    'exc'    => [...],
    'event'  => [...],
];

$ta = new TransientAnalysis($data);
$ta->run();

$results = $ta->getData();
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
```

---

## License

This project is licensed under the **GNU General Public License v2.0 or later**. See the [LICENSE](LICENSE) file for details.
