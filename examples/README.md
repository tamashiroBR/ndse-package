# NDSE — Example Data Files

This directory contains IEEE standard test system data files used as input examples for the NDSE library. All data sets were extracted from the appendix of the doctoral thesis that originated this software:

> SENA, J. A. D. S. **Desenvolvimento de Framework para Análise e Simulação Dinâmica de Sistemas Elétricos de Potência**. Doctoral Thesis — Universidade Federal de Uberlândia, Faculdade de Engenharia Elétrica. Available at: https://repositorio.ufu.br/handle/123456789/18396

---

## Available Examples

| File | System | Type | Buses | Branches |
|---|---|---|---|---|
| `loadflow_9bus.json` | IEEE 9-Bus | Load Flow | 9 | 9 |
| `loadflow_57bus.json` | IEEE 57-Bus | Load Flow | 57 | 80 |
| `loadflow_118bus.json` | IEEE 118-Bus | Load Flow | 118 | 186 |
| `stability_9bus.json` | IEEE 9-Bus | Transient Stability | 9 | 9 |

---

## JSON Input Format

### Load Flow (`"info": ["lf"]`)

```json
{
    "info": ["lf"],
    "optLF": [baseMVA, maxIter, tolerance, method],
    "bus": [
        [id, type, Pg, Qg, Pd, Qd, Gs, Vm, Va, Qmax, Qmin],
        ...
    ],
    "branch": [
        [from, to, R, X, B, tap, shift, status],
        ...
    ]
}
```

### Transient Stability Analysis (`"info": ["ta"]`)

```json
{
    "info": ["ta"],
    "optLF": [baseMVA, maxIter, tolerance, method],
    "optTA": [tmax, t0, method, dt],
    "bus": [ ... ],
    "branch": [ ... ],
    "gen": [
        [bus, model, H, D, Xd, Xdp, Xq, Xqp, Td0p, Tq0p],
        ...
    ],
    "exc": [
        [bus, model, Ka, Ta, Vmin, Vmax, Ke, Te, Kf, Tf, E1, Se1],
        ...
    ],
    "gov": [],
    "event": [
        [type, fromBus, toBus, time, impedance],
        ...
    ]
}
```

---

## Bus Types

| Code | Description |
|---|---|
| `1` | PQ Bus (load bus) |
| `2` | PV Bus (generator bus) |
| `3` | Slack Bus (reference bus) |

## Branch Parameters

| Field | Description |
|---|---|
| `R` | Resistance (p.u.) |
| `X` | Reactance (p.u.) |
| `B` | Total susceptance (p.u.) |
| `tap` | Transformer tap ratio (0 = transmission line) |
| `shift` | Phase shift angle (degrees) |
| `status` | 1 = in service, 0 = out of service |

---

## References

- **IEEE 9-Bus System**: Anderson, P. M.; Fouad, A. A. *Power System Control and Stability*. IEEE Press, 1994.
- **IEEE 57-Bus System**: Freris, L. L.; Sasson, A. M. Investigation of the load-flow problem. *Proceedings of the IEE*, v. 115, n. 10, pp. 1459–1470, 1968.
- **IEEE 118-Bus System**: Illinois Institute of Technology. *IEEE 118-Bus Test Case*, 1962.
