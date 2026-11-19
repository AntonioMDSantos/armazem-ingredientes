Ambiguidades encontradas no enunciado e como cada uma foi resolvida.

1 A capacidade depende do tipo armazenado no compartimento: SECO 600 kg, LIQUIDO 500 L, REFRIGERADO 400 kg.
Um compartimento vazio assume a capacidade do tipo que entrar.

2 Guardo só a quantidade, decimal(10, 3). A unidade (kg/L) é derivada do tipo.
Toda a aritmética usa bcmath com strings: float acumula erro de arredondamento (0,1 + 0,2 =/ 0,3)

3 O exemplo de request usa "SECOS" e o de response usa "SECO". O enum canônico fica no singular
(SECO, LIQUIDO, REFRIGERADO), ea  entrada aceita plural, minusculas e acento.

4 TIPO = categoria
Um compartimento guarda uma categoria por vez. Farinha e açucar (ambos SECOS)
podem dividir o C1

5 O enunciado cita secos, mas a regra foi generalizada para todos os tipos. Se um compartimento recebeu uma entrada de um tipo hoje, só poderá receber outro tipo amanhã, mesmo que tenha esvaziado.
Data fuso America/Sao_Paulo

6 Post/Ingredientes é uma ENTRADA
Se oo mesm nome ja existe no mesmo compartimento, soma ao estoque. Senão, cria.
Sempre gera historico
Resposta 201 nos dois casos, porque uma movimentação nova sempre é criada.


7 Saída
O enunciado não define endpoint de saida, mas exige histoico de saida e consulta para venda
Foi criadoo POST /ingredientes/{id}/saida.

8 Erros
Regra de negocio violada: 400, com { error, message }
Payload invalido: 400, com errors por campo.
recurso inexistente: 404

9 Historico
Padrao: data desc, compartimento asc. sortBy=date|compartimento, order=asc|desc (whitelist), paginado
occurred_at com microssegundos: com precisao de segundos, movimentação do mesmo segundo empatam
e o desempate por compartimento quebra a ordem cronologica.

10 Modelo
compartimentos: codigo, tipo_atual, tipo_definido_em, quantidade_atual (desnormalizado)
ingredientes: compartimento_id, nome, tipo, quantidade, com unique(compartimento_id, nome)
movimentacoes: ingrediente_id, compartimento_id, operacao, tipo_ingrediente, quantidade, responsavel, occurred_at
quantidade_atual é desnormalizado: o compartimento vira a unidade de consistência. Uma linha travada
responde tipo, saldo e trava diária, e a consulta de disponibilidade vira uma query sem SUM.

O histórico guarda tipo e compartimento mesmo sendo deriváveis do ingrediente: é a foto do momento.

11 Concorrencia
oda mutação passa pelo EstoqueService, em transação, com lock pessimista na linha do compartimento
(SELECT ... FOR UPDATE). A ordem dos locks é fixa (compartimento, depois ingrediente) para evitar deadlock.
Leituras do que vai ser alterado usam lock (FOR UPDATE), o que garante o último valor commitado em qualquer
nível de isolamento. O PostgreSQL usa READ COMMITTED por padrão; o código também funciona em REPEATABLE READ (MySQL).

Lock otimista (coluna version + retry) foi descartado: com 5 linhas disputadas a contenção é alta e os
retries virariam regra.

PostgreSQL na execução e nos testes: SQLite não tem lock de linha.

12 Nome do ingrediente
No PostgreSQL a comparação de texto diferencia maiúsculas: "Farinha" e "farinha" seriam dois ingredientes.
O nome é gravado como enviado (com trim). Normalizar ou usar CITEXT fica como melhoria.

13 Diponibilidade
Ordenação best-fit: o compartimento com menor espaço livre que ainda comporta vem primeiro.
Isso reduz fragmentação e mantém compartimentos vazios livres para cargas grandes ou outro tipo.
A consulta não reserva nada; a entrada revalida tudo com lock.

14 Endpoints
Método	Rota	Descrição
POST	/api/ingredientes	Entrada (cria ou soma)
GET	/api/ingredientes	Lista paginada
GET	/api/ingredientes/{id}	Detalhe
POST	/api/ingredientes/{id}/saida	Saída
GET	/api/ingredientes/volume	Total por tipo
GET	/api/compartimentos	Situação dos 5 compartimentos
GET	/api/compartimentos/disponiveis?quantidade=&tipo=	Onde cabe (best-fit)
GET	/api/compartimentos/disponiveis-para-venda?tipo=	Onde há saldo do tipo
GET	/api/historico?sortBy=&order=&perPage=	Movimentações