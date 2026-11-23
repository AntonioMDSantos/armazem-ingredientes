# Armazém de Ingredientes

API REST para controlar o estoque de ingredientes de uma fábrica de alimentos, distribuídos em 5 compartimentos (C1 a C5). Cada compartimento guarda um tipo por vez, tem capacidade definida pelo tipo e só troca de tipo no dia seguinte. Toda entrada e saída gera histórico.

Laravel 13, PHP 8.3 e PostgreSQL 16. As decisões de projeto e as ambiguidades do enunciado estão detalhadas em [DECISOES.md](DECISOES.md); o resumo está [mais abaixo](#resumo-das-decisões).

## Sumário

- [Como rodar](#como-rodar)
- [Testes](#testes)
- [Script de concorrência](#script-de-concorrência)
- [Endpoints](#endpoints)
- [Códigos de erro](#códigos-de-erro)
- [Arquitetura de pastas](#arquitetura-de-pastas)
- [Resumo das decisões](#resumo-das-decisões)
- [Com mais tempo](#com-mais-tempo)

## Como rodar

### Requisitos

- PHP 8.3+ com as extensões `pdo_pgsql`, `bcmath` e `curl`
- Composer
- PostgreSQL 16 (local ou via Docker)

O banco precisa ser PostgreSQL: o controle de concorrência usa `SELECT ... FOR UPDATE`, e o SQLite não tem lock de linha.

### 1. Banco de dados

São dois bancos: `armazem` para a aplicação e `armazem_test` para os testes.

**Com Docker:**

```bash
docker run -d --name armazem-db \
  -e POSTGRES_DB=armazem -e POSTGRES_USER=armazem -e POSTGRES_PASSWORD=armazem \
  -p 5432:5432 postgres:16-alpine

docker exec armazem-db createdb -U armazem armazem_test
```

**Com PostgreSQL instalado na máquina:**

```bash
sudo -u postgres psql -c "CREATE ROLE armazem LOGIN PASSWORD 'armazem';"
sudo -u postgres createdb -O armazem armazem
sudo -u postgres createdb -O armazem armazem_test
```

### 2. Aplicação

```bash
composer install
cp .env.example .env          # ajuste DB_PASSWORD=armazem
php artisan key:generate
php artisan migrate --seed    # cria as tabelas e os compartimentos C1..C5
php artisan serve
```

A API fica em `http://localhost:8000/api`. O arquivo [request.http](request.http) tem exemplos prontos para a extensão REST Client do VS Code.

Para voltar ao estado inicial, com os 5 compartimentos vazios:

```bash
php artisan migrate:fresh --seed
```

O fuso da aplicação é `America/Sao_Paulo` ([config/app.php](config/app.php)). A virada do dia da trava de tipo segue esse fuso.

## Testes

```bash
php artisan test
```

São 120 testes. Eles rodam no banco `armazem_test`, configurado em [phpunit.xml](phpunit.xml), e nunca tocam no banco `armazem`. Cada teste começa com o banco recriado e C1..C5 vazios, com o relógio fixado às 10h para a trava diária não depender da hora da execução.

| Arquivo | Cobre |
|---|---|
| `tests/Unit/TipoIngredienteTest.php` | Normalização do tipo (plural, minúsculas, acento), capacidade e unidade |
| `tests/Feature/EntradaIngredienteTest.php` | Capacidade, precisão decimal, tipo, categoria, trava do dia, cria/soma, validação, nome |
| `tests/Feature/SaidaIngredienteTest.php` | Saída, estoque insuficiente, 404, saída não renova a trava |
| `tests/Feature/CompartimentoConsultaTest.php` | Best-fit, exclusão por capacidade, tipo e trava; disponíveis para venda; parâmetros |
| `tests/Feature/VolumeEHistoricoTest.php` | Formato do volume, ordenação do histórico, regressão de mesmo segundo, whitelist |

Toda rejeição é verificada da mesma forma: status, campo `error`, nenhuma movimentação nova e estoque intacto, incluindo tipo e data do tipo de cada compartimento.

Para rodar só uma parte:

```bash
php artisan test --filter=SaidaIngredienteTest
php artisan test --testsuite=Unit
```

## Script de concorrência

[scripts/concorrencia.php](scripts/concorrencia.php) dispara entradas simultâneas no mesmo compartimento até passar da capacidade. Depois dispara saídas simultâneas do mesmo ingrediente até passar do saldo. No fim, confere se o estoque continua consistente. As requisições saem todas ao mesmo tempo, via `curl_multi`.

### Como rodar

Em um terminal, suba o servidor **com vários workers**:

```bash
php artisan migrate:fresh --seed
PHP_CLI_SERVER_WORKERS=20 php artisan serve --no-reload
```

Em outro terminal:

```bash
php scripts/concorrencia.php
```

O `--no-reload` é obrigatório. Sem ele, o `artisan serve` ignora `PHP_CLI_SERVER_WORKERS` e sobe um único worker. As requisições seriam atendidas uma de cada vez e o script não testaria concorrência nenhuma: passaria, mas sem provar nada.

O script grava dados de verdade e exige que o compartimento-alvo esteja vazio e sem outro tipo. Por isso, rode depois de um `migrate:fresh --seed` ou escolha outro compartimento.

| Opção | Padrão | Descrição |
|---|---|---|
| `--base` | `http://127.0.0.1:8000/api` | URL base da API |
| `--compartimento` | `1` | Id do compartimento-alvo |
| `--requisicoes` | `20` | Requisições simultâneas em cada fase |
| `--quantidade` | `50` | Kg de SECO por requisição |

### Resultado esperado

Com os valores padrão, 20 × 50 kg disputam 600 kg de capacidade. Só 12 cabem.

```
C1: 20 entradas simultâneas de 50 kg de SECO (capacidade 600 kg).

  Entradas: 0 x 200, 12 x 201, 0 x 5xx. Erros: 8 CAPACIDADE_EXCEDIDA
  [ok]    nenhuma resposta 5xx
  [ok]    12 entradas aceitas (aceitas: 12)
  [ok]    8 rejeitadas com CAPACIDADE_EXCEDIDA
  [ok]    saldo do compartimento = 600.000 (lido: 600.000)
  [ok]    saldo não passa da capacidade de 600
  [ok]    ingrediente soma o mesmo que o compartimento (600.000)
  [ok]    uma movimentação ENTRADA por entrada aceita

C1: 20 saídas simultâneas de 50 kg (saldo 600.000 kg).

  Saídas: 12 x 200, 0 x 201, 0 x 5xx. Erros: 8 ESTOQUE_INSUFICIENTE
  [ok]    nenhuma resposta 5xx
  [ok]    12 saídas aceitas (aceitas: 12)
  [ok]    8 rejeitadas com ESTOQUE_INSUFICIENTE
  [ok]    saldo final = 0.000 (lido: 0.000)
  [ok]    saldo nunca fica negativo
  [ok]    ingrediente e compartimento continuam iguais
  [ok]    uma movimentação SAIDA por saída aceita

OK: estoque consistente.
```

O script sai com código `0` quando tudo bate e `1` quando encontra inconsistência. Assim, dá para usar em CI.

## Endpoints

Todas as rotas ficam sob `/api` e respondem JSON. Os campos de quantidade aceitam até 3 casas decimais.

| Método | Rota | Descrição | Sucesso |
|---|---|---|---|
| `POST` | `/ingredientes` | Entrada: cria o ingrediente ou soma ao existente (mesmo nome no mesmo compartimento) | `201` |
| `GET` | `/ingredientes` | Lista paginada (50 por página), por ordem de cadastro | `200` |
| `GET` | `/ingredientes/{id}` | Detalhe de um ingrediente | `200` |
| `POST` | `/ingredientes/{id}/saida` | Saída de estoque | `200` |
| `GET` | `/ingredientes/volume` | Total armazenado por tipo, sempre com os 3 tipos | `200` |
| `GET` | `/compartimentos` | Situação dos 5 compartimentos | `200` |
| `GET` | `/compartimentos/disponiveis?tipo=&quantidade=` | Onde a quantidade cabe, do mais justo ao mais folgado (best-fit) | `200` |
| `GET` | `/compartimentos/disponiveis-para-venda?tipo=` | Compartimentos com saldo > 0 do tipo, do maior saldo para o menor | `200` |
| `GET` | `/historico?sortBy=&order=&perPage=` | Movimentações paginadas | `200` |

**Entrada** (`POST /ingredientes`), no formato do enunciado:

```json
{ "nome": "Farinha de Trigo", "tipo": "SECOS", "quantidade": 400, "compartimentoId": 1, "responsavel": "pedro" }
```

O campo `tipo` aceita singular ou plural, maiúsculas ou minúsculas, com ou sem acento (`SECOS`, `seco`, `Líquidos`). A resposta usa sempre o valor canônico: `SECO`, `LIQUIDO` ou `REFRIGERADO`.

**Saída** (`POST /ingredientes/{id}/saida`):

```json
{ "quantidade": 150, "responsavel": "ana" }
```

**Volume** (`GET /ingredientes/volume`) devolve o array na raiz, como no enunciado:

```json
[
  { "type": "LIQUIDO", "totalQuantity": 200 },
  { "type": "REFRIGERADO", "totalQuantity": 150 },
  { "type": "SECO", "totalQuantity": 400 }
]
```

**Histórico** (`GET /historico`):

| Parâmetro | Valores | Padrão |
|---|---|---|
| `sortBy` | `date`, `compartimento` | `date` |
| `order` | `asc`, `desc` | `desc` |
| `perPage` | `1` a `100` | `20` |

- Com `sortBy=date`, o desempate é por compartimento asc.
- Com `sortBy=compartimento`, o desempate é pela movimentação mais recente.
- Valores fora da whitelist retornam `400`.
- `dataHora` vem em ISO 8601 com milissegundos, por exemplo `2026-11-23T10:15:30.123-03:00`.

## Códigos de erro

Todo erro responde com `{ "error": "<CODIGO>", "message": "<texto>" }`. Erros de validação trazem também `errors`, com a lista de mensagens por campo.

| Código | HTTP | Quando |
|---|---|---|
| `CAPACIDADE_EXCEDIDA` | `400` | A entrada faria o compartimento passar da capacidade do tipo (SECO 600 kg, LIQUIDO 500 L, REFRIGERADO 400 kg) |
| `TIPO_INCOMPATIVEL` | `400` | O compartimento tem saldo de outro tipo |
| `COMPARTIMENTO_TRAVADO_NO_DIA` | `400` | O compartimento está vazio, mas recebeu outro tipo hoje; só aceita um tipo diferente amanhã |
| `ESTOQUE_INSUFICIENTE` | `400` | A saída é maior que o saldo do ingrediente |
| `VALIDACAO` | `400` | Payload ou parâmetro inválido: campo faltando, tipo desconhecido, quantidade ≤ 0 ou com mais de 3 casas, compartimento inexistente, parâmetro do histórico fora da whitelist |
| `NAO_ENCONTRADO` | `404` | Ingrediente ou rota inexistente |

Exemplo:

```json
{ "error": "CAPACIDADE_EXCEDIDA", "message": "C1 suporta 600.000 kg de SECO. Ocupado: 400.000. Solicitado: 250." }
```

A validação usa `400` em vez do `422` padrão do Laravel, porque o enunciado pede `400 BAD REQUEST`. Os handlers ficam em [bootstrap/app.php](bootstrap/app.php).

## Arquitetura de pastas

```
app/
├── Data/EntradaData.php            DTO da entrada (request → service)
├── Enums/
│   ├── TipoIngrediente.php         SECO/LIQUIDO/REFRIGERADO, capacidade, unidade, normalização da entrada
│   └── TipoOperacao.php            ENTRADA/SAIDA
├── Exceptions/Domain/              Uma exception por regra de negócio, cada uma com seu código
│   └── RegraDeNegocioException.php Base: vira 400 { error, message } em bootstrap/app.php
├── Http/
│   ├── Controllers/Api/            Controllers finos: validam via Form Request, chamam o service, devolvem Resource
│   ├── Requests/                   Validação; Concerns/ normaliza o tipo e entrega quantidade como string
│   └── Resources/                  Formato de saída em camelCase; quantidade vira float só aqui
├── Models/                         Compartimento, Ingrediente, Movimentacao (casts de enum e decimal:3)
└── Services/
    ├── EstoqueService.php          Toda escrita: transação, lock pessimista, regras, histórico
    └── ConsultaService.php         Leituras: disponíveis (best-fit), venda, volume, histórico

database/
├── migrations/                     compartimentos, ingredientes, movimentacoes
└── seeders/CompartimentoSeeder.php C1..C5 (idempotente)

routes/api.php                      Rotas; /ingredientes/volume antes de /ingredientes/{id}
scripts/concorrencia.php            Teste de carga concorrente contra o servidor rodando
tests/
├── Feature/ApiTestCase.php         Base: banco limpo, seed, relógio fixo, assertSemEfeito
├── Feature/                        Testes de API, um arquivo por área
└── Unit/                           Enum
```

**Fluxo de uma escrita:** Form Request (valida e normaliza) → Controller → `EstoqueService` (transação + lock + regras + histórico) → Resource.

Regra de negócio só existe no service. Uma violação lança uma `RegraDeNegocioException`, que o handler converte em `400`.

## Resumo das decisões

Detalhes e justificativas em [DECISOES.md](DECISOES.md).

1. **Capacidade depende do tipo armazenado** (SECO 600 kg, LIQUIDO 500 L, REFRIGERADO 400 kg). Um compartimento vazio assume a capacidade do tipo que entrar.
2. **Quantidades em `decimal(10,3)` e aritmética com bcmath**, nunca float. A unidade é derivada do tipo; o float só aparece na serialização JSON.
3. **Enum canônico no singular.** A entrada aceita plural, minúsculas e acento, porque o enunciado usa `SECOS` no request e `SECO` no response.
4. **Tipo = categoria.** Farinha e açúcar, ambos SECO, podem dividir o mesmo compartimento.
5. **Trava diária generalizada para todos os tipos.** Um compartimento que recebeu um tipo hoje só aceita outro tipo amanhã, mesmo que tenha sido esvaziado. A virada do dia segue o fuso de São Paulo, e só a entrada define a trava (a saída não a renova).
6. **`POST /ingredientes` é uma entrada.** Mesmo nome no mesmo compartimento soma; senão, cria. Sempre gera histórico e sempre responde `201`.
7. **Saída em `POST /ingredientes/{id}/saida`.** O enunciado exige histórico de saída, mas não define o endpoint.
8. **Erros:** regra de negócio → `400 { error, message }`; payload inválido → `400` com `errors` por campo; recurso inexistente → `404`.
9. **Histórico em `timestamp(6)`.** Com precisão de segundos, movimentações do mesmo segundo empatariam e o desempate por compartimento quebraria a ordem cronológica.
10. **`quantidade_atual` desnormalizado no compartimento.** Uma única linha travada responde tipo, saldo e trava, e a consulta de disponibilidade não precisa de `SUM`. O histórico guarda tipo e compartimento como estavam no momento da movimentação.
11. **Lock pessimista** (`SELECT ... FOR UPDATE`) na linha do compartimento, em transação, com ordem fixa de locks (compartimento → ingrediente) para evitar deadlock. O lock otimista foi descartado: com só 5 linhas disputadas, retries virariam regra.
12. **Nome gravado como enviado, com trim.** "Farinha" e "farinha" são ingredientes distintos.
13. **Disponibilidade em best-fit**, do menor espaço livre que comporta ao maior. Isso reduz a fragmentação e preserva compartimentos vazios. A consulta não reserva nada: a entrada revalida tudo com lock.

## Com mais tempo

- **Nome sem diferenciar maiúsculas:** usar `CITEXT` ou uma coluna normalizada com índice único, para "Farinha" e "farinha" serem o mesmo ingrediente.
- **Idempotência no POST:** aceitar um cabeçalho `Idempotency-Key`, para que um retry do cliente após timeout não duplique a entrada.
- **Constraints no banco:** `CHECK (quantidade >= 0)` nas tabelas e limite de capacidade no compartimento, como última linha de defesa além do service.
- **Concorrência no CI:** rodar `scripts/concorrencia.php` no pipeline, com um serviço PostgreSQL e o servidor com workers.
- **Histórico mais rico:** filtros por compartimento, ingrediente, tipo e período, e paginação por cursor para volumes grandes.
- **Mensagens de validação em português:** instalar as traduções pt-BR do Laravel e usar `APP_LOCALE=pt_BR`.
- **Autenticação:** o `responsavel` viria do usuário autenticado em vez do payload.
- **Documentação OpenAPI** gerada a partir dos Form Requests e Resources.
- **Docker Compose** com app e banco, para subir tudo com um comando.
- **Capacidade configurável por compartimento**, hoje fixa por tipo no enum.
