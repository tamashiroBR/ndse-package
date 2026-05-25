# NDSE package

Um pacote PHP para simulação dinâmica de redes elétricas, com suporte a cálculo de fluxo de carga (Load Flow) e análise de estabilidade transitória (Transient Stability Analysis).

O software é fruto de uma tese de doutorado defendida na Universidade Federal de Uberlândia, Faculdade de Engenharia Elétrica, com o documento disponível no repositório: https://repositorio.ufu.br/handle/123456789/18396.

## Instalação

Você pode instalar este pacote via Composer:

```bash
composer require tamashiro/ndse
```

## Requisitos

- PHP >= 7.4

## Funcionalidades

- **Load Flow (Fluxo de Carga)**: Cálculo de fluxo de carga em sistemas de potência utilizando o método de Newton-Raphson.
- **Transient Analysis (Análise Transitória)**: Simulação no domínio do tempo utilizando método de integração trapezoidal implícito.
- **Modelos de Equipamentos**: 
  - Geradores síncronos (Gen1, Gen2)
  - Sistemas de excitação (Exc1, Exc2)
- **Matemática e Matrizes**: Suporte nativo para operações com matrizes densas e esparsas, bem como números complexos.

## Uso Básico

### Fluxo de Carga

```php
use NDSE\Tools\LoadFlow;

// Configurar opções e dados da rede
$data = [
    'optLF' => [100, 20, 1e-3, 1], // sbase, max_iter, tol, qlim
    'bus' => [...],
    'branch' => [...]
];

// Instanciar a ferramenta e executar
$lf = new LoadFlow($data);
$lf->makeYbus();
$lf->run();

// Obter resultados
$results = $lf->getData();
```

### Análise de Estabilidade Transitória

```php
use NDSE\Tools\TransientAnalysis;

// Configurar opções e dados dinâmicos
$data = [
    'optLF' => [100, 20, 1e-3, 1],
    'optTA' => [60, 0, 10, 0.01], // fbase, tstart, tstop, step
    'bus' => [...],
    'branch' => [...],
    'gen' => [...],
    'exc' => [...],
    'event' => [...]
];

// Instanciar e executar
$ta = new TransientAnalysis($data);
$ta->run();

// Obter resultados da simulação
$results = $ta->getData();
```

## Estrutura do Pacote

- `src/Tools/`: Ferramentas principais (`LoadFlow`, `TransientAnalysis`).
- `src/Models/`: Modelos de componentes dinâmicos (Geradores, Excitadores, Governadores).
- `src/Math/`: Utilitários matemáticos (Matrizes Esparsas, Números Complexos, Álgebra Linear).

## Licença

Este projeto é licenciado sob a GPL-2.0-or-later. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.
