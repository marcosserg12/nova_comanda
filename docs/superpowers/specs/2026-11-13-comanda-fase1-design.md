# Nana Comanda — Fase 1: núcleo de pedidos (design)

- **Data:** 2026-11-13
- **Status:** revisado pelo usuário em 2026-11-13. Pronto para o plano de implementação.
- **Projeto:** reescrita do zero do sistema de comandas (TCC em CodeIgniter 3) para uso real na Nana Lanches.
- **Referência (somente leitura):** `c:\laragon\www\Comanda_refatorada`. Não usar o git desse repositório.

---

## 1. Contexto e objetivo

Hoje a Nana Lanches trabalha no papel. A mãe anota o pedido no balcão, entrega o papel na cozinha e grita o nome do cliente quando o prato fica pronto. O pagamento acontece **depois** de comer, numa maquininha Santander/Getnet que não tem integração com nada.

A fase 1 substitui o papel e o grito por um sistema simples:

1. o balcão lança o pedido;
2. a cozinha vê o pedido na hora e confirma quando fica pronto;
3. a TV do salão e o celular do cliente mostram o andamento e chamam o cliente pelo número e pelo nome.

**Critérios de sucesso**
- Um pedido lançado no balcão aparece na cozinha em menos de 1 segundo, com conexão normal.
- A mãe lança um pedido típico (1 prato + 1 suco + observação) em menos de 15 segundos.
- Nenhum pedido se perde nem sai duplicado quando a conexão oscila.
- A TV chama o cliente sem precisar de interação manual.

### Fora do escopo da fase 1
- Pagamento (registro ou integração), estoque, boletos e gráficos.
- NFC-e. Existe um emissor separado, o `nananotas`, que esta fase não toca.
- Totem de autoatendimento e maquininha integrada (fase 2, ver seção 12).
- Aviso por WhatsApp e cadastro de telefone do cliente.
- Impressão de tickets.

---

## 2. Decisões tomadas

| Tema | Decisão |
|---|---|
| Framework | Laravel na versão estável atual (PHP 8.3), com Livewire, Filament, Reverb e Tailwind |
| Tempo real | Reverb (WebSocket), mais uma verificação de segurança a cada 20s |
| Hospedagem | VPS própria (Ubuntu 24.04, compartilhada com outros projetos), no endereço `comanda.mswebsolutions.site` com HTTPS |
| Pedido | Vários itens, observação por item, para viagem, total calculado |
| Identificação | Número do dia (001, 002…, zera diariamente) + nome do cliente |
| Destino dos itens | Cada categoria vai para a `cozinha` (pratos, sucos) ou fica na `cabine` (refri etc., lançamento opcional) |
| Cardápio | Fixo, com "Prato do dia" de nome variável e botão "Esgotado" que vale só para o dia |
| Cozinha | Monitor ligado ao **PC do balcão**, que dirige as duas telas. A cozinha confirma "Pronto" por **toque ou teclado numérico** (o software aceita os dois) |
| "Pronto" no balcão | Também disponível, como reserva |
| Acompanhamento do cliente | TV no salão + QR code fixo que abre a mesma tela no celular |
| Pagamento | Não registrado na fase 1; o balcão mostra o total para a cobrança |
| Código | Pasta local `c:\laragon\www\nana-comanda` com um git novo, sem relação com o repositório do TCC. Remoto: `https://github.com/marcosserg12/nova_comanda.git` |
| Preços | Editáveis pelo admin direto na lista de produtos. O histórico não muda, porque cada item guarda o preço da hora do pedido |

---

## 3. Arquitetura

### 3.1 Componentes
- **Laravel** (estável atual) rodando em PHP 8.3.
- **Livewire** para as telas operacionais: estação de atendimento, balcão, cozinha, painel da TV e acompanhamento.
- **Filament** para a administração: cardápio, usuários, histórico, resumo de vendas e configurações.
- **Reverb + Laravel Echo** para o tempo real.
- **Tailwind** para o visual, nas cores da marca (vermelho e amarelo da placa).
- **MySQL 8.** Sessão, cache e fila ficam no banco (driver `database`). Não há Redis: o Redis que existe na VPS pertence ao n8n e não deve ser compartilhado.
- **Broadcast síncrono** (`ShouldBroadcastNow`, disparado após o commit). Na fase 1 não existe worker de fila.
- **Laravel Boost** como dependência de desenvolvimento. Ele instala as diretrizes e skills de IA adequadas às versões instaladas.

### 3.2 Produção (VPS compartilhada)
- O código fica em `/var/www/comanda` e roda no pool PHP 8.3-FPM existente.
- **Banco:** `comanda`, com um usuário próprio que só tem permissão nesse banco.
- **nginx:** site novo `comanda.mswebsolutions.site`, com HTTPS via certbot. Sempre rodar `nginx -t` antes de recarregar, e nunca editar os sites dos outros projetos.
- **Reverb:** serviço systemd `comanda-reverb`, escutando em `127.0.0.1:8080` (porta hoje livre). O nginx faz proxy do WebSocket (`/app`, `/apps`) para ele.
- **Agendador:** um cron chama `schedule:run` para o backup diário.
- **Backup:** `mysqldump` diário do banco `comanda` em `/var/backups/comanda`, guardando 14 dias.
- **Deploy:** um script que baixa o código (git), roda `composer install --no-dev`, `npm ci && npm run build` (a VPS tem Node 20), `migrate --force` e os caches, e por fim reinicia o `comanda-reverb`.
  - **Remoto:** `github.com/marcosserg12/nova_comanda`. A VPS baixa o código usando uma **chave de deploy somente leitura**, gerada na VPS e cadastrada no GitHub em Settings → Deploy keys.

### 3.3 Desenvolvimento
- Laragon (PHP 8.3, MySQL 8.4), no endereço `nana-comanda.test`.
- Reverb local com `php artisan reverb:start`.

### 3.4 Telas, endereços e perfis

| Endereço | Uso | Acesso |
|---|---|---|
| `/estacao` | **Uma única janela cobrindo os dois monitores do PC do balcão**: o balcão à esquerda e a cozinha à direita | `balcao`, `admin` |
| `/balcao` | Só o balcão (para outros arranjos) | `balcao`, `admin` |
| `/cozinha` | Só a cozinha (plano B: aparelho próprio na cozinha) | `cozinha`, `balcao`, `admin` |
| `/painel` | TV do salão | público, somente leitura |
| `/acompanhar` | Celular do cliente (QR code) | público, somente leitura |
| `/admin` | Filament | `admin` |

- **Perfis:**
  - `admin`: o pai ou o desenvolvedor; acessa tudo.
  - `balcao`: a mãe.
  - `cozinha`: só usado se a cozinha ganhar um aparelho próprio.
- O login é por **usuário + senha**, com "lembrar de mim" de longa duração para o PC da loja não pedir senha todo dia.

**Por que uma janela única em `/estacao`:** no Windows, tocar ou clicar numa segunda janela tira o foco do teclado da primeira. Com uma janela só, que ocupa os dois monitores, nunca há troca de janela. Além disso, os botões da área da cozinha não pegam o foco (`pointerdown` + `preventDefault`), então o campo que a mãe está digitando continua ativo.

- **Como a janela é aberta:** um atalho do Chrome em modo app: `--app=https://comanda.mswebsolutions.site/estacao --window-position=0,0 --window-size=<L1+L2>,<A>`.
- **Divisão das áreas:** a largura de cada lado vem das configurações (pixels do monitor 1 e do monitor 2).
- **Validação:** isso precisa ser testado com o hardware real (ver seção 11).

---

## 4. Modelo de dados

Os nomes no código seguem o padrão do Laravel, em inglês. Toda a interface é em português.

**`users`**: `id`, `name`, `username` (único), `password`, `role` (`admin` | `balcao` | `cozinha`), `remember_token`, timestamps.

**`categories`**: `id`, `name`, `destination` (`cozinha` | `cabine`), `position`, `active`.

**`products`**
- Campos básicos: `id`, `category_id`, `name`, `price_cents` (inteiro), `image_path` (opcional), `active`, `position`, timestamps.
- `has_daily_name` (bool): para "Prato do dia" e "Prato Econômico".
- `daily_name` + `daily_name_date`: o nome do dia só vale se `daily_name_date` for hoje. Caso contrário, o balcão pede para escolher o prato de hoje.
- `sold_out_date`: o produto está esgotado se esse campo for hoje. No dia seguinte ele volta sozinho.

**`quick_notes`**: `id`, `category_id`, `label`, `position`. São os botões de observação de cada categoria.

**`orders`**
- Identificação: `id`, `uuid` (único; chave de idempotência gerada pela tela), `business_date`, `number`. A combinação `business_date` + `number` é única.
- Dados do pedido: `customer_name`, `takeaway` (bool), `total_cents`, `created_by` (usuário).
- `status`: `preparing` | `ready` | `delivered` | `canceled`.
- `source`: `balcao` | `totem` (o totem entra na fase 2).
- Horários: `ready_at`, `delivered_at`, `canceled_at`, `edited_at`, timestamps.
- `cancel_reason`.

**`order_items`**
- `id`, `order_id`, `product_id` (fica nulo se o produto for excluído).
- Cópias congeladas no momento do pedido: `name_snapshot` (ex.: "Prato do dia: Strogonoff"), `unit_price_cents`, `destination`.
- `quantity`, `notes`, `position`.

**`daily_counters`**: `business_date` (chave primária), `last_number`.

**`settings`**: `key`, `value` (json). Guarda:
- quanto tempo o pedido fica em "Prontos" na TV (padrão 5 min);
- o nome do restaurante;
- as larguras dos monitores da estação.

### Regras
- **Dia de negócio:** é sempre calculado no fuso `America/Sao_Paulo`.
- **Número do pedido:** é gerado numa transação com `lockForUpdate` em `daily_counters`. Dois pedidos simultâneos (balcão + totem no futuro) nunca recebem o mesmo número. O número aparece com 3 dígitos (`042`).
- **Total:** é a soma de `quantity × unit_price_cents` de todos os itens, incluindo os da cabine. É recalculado no servidor, e o valor enviado pela tela é ignorado.
- **Itens repetidos:** dois pratos iguais com observações diferentes viram duas linhas. Pratos iguais sem diferença viram uma linha com `quantity = 2`.
- **Nome na tela pública:** usa só o primeiro nome (o primeiro termo de `customer_name`).

---

## 5. Fluxo do pedido

```
Balcão lança ──► PREPARANDO ──"Pronto"──► PRONTO ──"Entregue" (opcional)──► ENTREGUE
                 (cozinha e TV)           (tela cheia + som na TV;
                                           fica em "Prontos" por N min)
De PREPARANDO ou PRONTO ──► CANCELADO (com motivo; continua no histórico)
```

| Ação | Quem | De → para |
|---|---|---|
| Lançar pedido | `balcao`, `admin` | → `preparing`. Se o pedido só tiver itens de cabine, já nasce `delivered` |
| Alterar itens | `balcao`, `admin` | Só em `preparing`. Preenche `edited_at`, e a cozinha vê **ALTERADO** com bip. Se depois da alteração não sobrar item de cozinha, o pedido vira `delivered` |
| Marcar pronto | `cozinha`, `balcao`, `admin` | `preparing` → `ready` (preenche `ready_at`) |
| Desfazer pronto | Quem marcou, em até 10s pela notificação; `balcao`/`admin` a qualquer momento antes da entrega | `ready` → `preparing` |
| Marcar entregue | `balcao`, `admin` (opcional) | `ready` → `delivered` |
| Cancelar | `balcao`, `admin` | `preparing`/`ready` → `canceled`. Motivo com opções rápidas: "Cliente desistiu", "Lançado errado", "Outro" |

- **Validação:** toda transição é checada no servidor. Uma transição inválida ou repetida (por exemplo, dois "Pronto" ao mesmo tempo) não tem efeito e não gera erro para o usuário.
- **Lista "Prontos" da TV:** mostra pedidos com `status = ready` e `ready_at` dentro dos últimos N minutos.

---

## 6. Telas

### 6.1 Estação de atendimento (`/estacao`): lado do balcão
- **Abas de categoria** (Pratos | Sucos | Bebidas) com botões grandes, cada um com nome e preço.
  - Produto esgotado aparece apagado e não pode ser clicado.
  - O "Prato do dia" mostra o nome de hoje, ou "definir prato de hoje".
- **Busca por texto:** "pic" encontra Picanha. O Enter adiciona o primeiro resultado.
- **Painel do pedido:**
  - campo **Nome** (foco automático);
  - opção **Para viagem**;
  - lista de itens com quantidade −/+, chips de observação rápida, observação livre e botão remover;
  - **Total**;
  - botão **ENVIAR PARA COZINHA** (também pela tecla Enter).
- **Depois de enviar:** aparece a confirmação "Pedido 042 enviado!", o formulário é limpo e o foco volta para o campo Nome.
- **Rascunho:** fica salvo no navegador (`localStorage`) e sobrevive a um F5 ou a uma queda de conexão.
- **Coluna "Na cozinha"**, sempre visível: pedidos em preparo, com botão **Pronto** grande.
- **Aba "Pedidos de hoje":**
  - busca por número ou nome;
  - mostra status, tempo de espera e **total** (usado para a cobrança depois de comer);
  - ações: Pronto, Entregue, Editar, Cancelar e voltar para preparo.

### 6.2 Estação: lado da cozinha (também disponível sozinha em `/cozinha`)
- **Cartões em ordem de chegada.** Cada cartão mostra:
  - **número + nome** em letra grande, legível a 2–3 metros;
  - etiqueta **VIAGEM**;
  - itens de cozinha no formato "2× Filé de frango, SEM ARROZ, C/ OVO";
  - selo **ALTERADO**, quando for o caso;
  - cronômetro colorido: verde até 10 min, amarelo até 20, vermelho acima disso.
- **Toque:** botão **PRONTO** ocupando a base do cartão, e notificação "Desfazer" por 10s.
- **Teclado numérico** (só as teclas do bloco numérico, identificadas por `event.code` = `Numpad*`, que o sistema intercepta para que não cheguem ao balcão):
  - um cartão fica **destacado**, começando pelo mais antigo;
  - os dígitos (`Numpad0`–`Numpad9`) digitam um número de pedido, que aparece em destaque no topo;
  - `NumpadEnter` marca Pronto no pedido digitado ou, se nada foi digitado, no cartão destacado;
  - `NumpadAdd` (`+`) move o destaque para o próximo cartão;
  - `NumpadDecimal` (`,`) apaga o número digitado;
  - `NumpadSubtract` (`−`) desfaz o último Pronto em até 10s.
  - A mãe usa o teclado principal e não deve usar o bloco numérico. Alternativa: um teclado compacto sem bloco numérico para ela.
  - Ressalva: o teclado da cozinha só funciona enquanto a janela da estação estiver em primeiro plano. Por isso o PC deve ficar dedicado ao sistema.
- **Bip** em pedido novo e em pedido alterado.
- **Indicador de conexão** (verde ou vermelho) e relógio.

### 6.3 Painel da TV (`/painel`)
- **"Preparando"** ocupa 2/3 da tela: grade de número + primeiro nome.
- **"Prontos"** ocupa 1/3, em faixa vermelha.
- **Anúncio em tela cheia** quando um pedido fica pronto: "**042 · MARIA, PEDIDO PRONTO**", com som, por 8s.
  - Vários anúncios ao mesmo tempo entram em fila.
  - Um anúncio desfeito antes de ser exibido sai da fila.
- **Canto da tela:** relógio e QR code "Acompanhe pelo celular", apontando para `/acompanhar`.

### 6.4 Acompanhamento no celular (`/acompanhar`)
- Mostra os mesmos dados do painel, em layout vertical.
- O cliente pode digitar o próprio número (fica salvo no navegador). O pedido dele aparece destacado e o celular vibra quando ficar pronto, se a página estiver aberta.

### 6.5 Administração (`/admin`, Filament)
- **Cardápio:** categorias, produtos (preço, foto, ordem, ativo, esgotado, nome do dia) e observações rápidas.
  - **Mudança rápida de preço:** o preço pode ser editado direto na lista de produtos (campo editável na tabela), sem abrir o cadastro. Vale para os pedidos novos; os pedidos antigos mantêm o preço da época.
- **Usuários.**
- **Histórico:** filtro por período, status e produto, com detalhe de cada pedido.
- **Resumo de vendas:** total vendido, número de pedidos, ticket médio e quantidade por produto no período.
- **Configurações:** tempo em "Prontos", nome do restaurante e larguras da estação.

### 6.6 Desbloqueio de navegador (som, tela acesa, tela cheia)
- Na primeira abertura, a cozinha, a estação e o painel mostram **"Toque para começar"**. Esse toque libera o som, ativa o Screen Wake Lock e coloca o painel em tela cheia.
- Na TV, o Fully Kiosk Browser deve ser configurado para liberar o som automaticamente e abrir o painel ao ligar.

---

## 7. Tempo real

- **Canal público `painel`:** recebe o evento `OrderBoardChanged` com apenas `{number, first_name, status, ready_at}`. Usado pela TV e pelo celular.
- **Canal privado `pedidos`:** exige `balcao`, `cozinha` ou `admin`. Recebe `OrderCreated`, `OrderUpdated` e `OrderStatusChanged`, levando só o `id` e o tipo do evento.
- **Os eventos apenas avisam.** Ao receber um evento, o componente Livewire **busca de novo a lista no servidor**. Assim a tela nunca diverge do banco, mesmo se perder eventos.
- **Reconexão:** ao reconectar, a tela recarrega tudo.
- **Verificação de segurança:** `wire:poll` a cada 20s em todas as telas operacionais e públicas.
- **Falha no broadcast:** se o Reverb estiver fora do ar, o erro é registrado no log e **não impede** que o pedido seja salvo. As telas se recuperam na próxima verificação.

---

## 8. Robustez e tratamento de erros

- **Internet cai durante o envio:**
  - o pedido continua na tela com a mensagem "Sem conexão, tente de novo";
  - o rascunho fica no `localStorage`;
  - o `uuid` do pedido garante que reenviar não cria duplicata.
- **Clique duplo:** o botão fica desativado durante o envio, e o `uuid` garante a idempotência.
- **Conflitos de estado** (pronto em pedido cancelado, alteração em pedido pronto): o servidor recusa, e a tela recarrega e mostra o estado real.
- **Virada do dia:** o número do pedido, o "Esgotado" e o nome do dia são calculados pela data de São Paulo. Não há rotina noturna.
- **Plano B operacional:** se a internet cair, a loja usa o roteamento 4G de um celular ou volta ao papel temporariamente. A fase 1 não tem modo offline.
- **Logs:** log diário do Laravel com rotação. `APP_DEBUG=false` em produção.

---

## 9. Segurança

Esta seção corrige os problemas encontrados no TCC:
- rotas que alteravam dados sem login;
- CSRF desligado;
- SQL montado com texto do usuário.

Medidas:
- **Autenticação e autorização:** toda ação passa por login e por uma policy de perfil. Nenhuma rota GET altera estado, e o CSRF fica ativo.
- **Senhas e login:** senhas com hash (padrão do Laravel). O login tem limite de tentativas.
- **Banco:** só Eloquent e query builder com parâmetros. Nenhuma concatenação de SQL.
- **Telas públicas:** são somente leitura, com limite de requisições, e expõem só o número, o primeiro nome e o status dos pedidos do momento.
- **Produção:** usuário de banco restrito ao banco `comanda`, HTTPS, `.env` fora do diretório público e `APP_DEBUG=false`.
- **VPS compartilhada:** não alterar a configuração de outros sites, nem o firewall (ufw), nem o MySQL global.

---

## 10. Testes

São escritos antes do código (TDD), com Pest.

- **Pedidos:**
  - a numeração diária começa em 1 e zera no dia seguinte;
  - dois pedidos simultâneos recebem números distintos;
  - o total é recalculado no servidor;
  - os snapshots de nome e preço ficam congelados;
  - um `uuid` repetido não duplica o pedido;
  - pedido só de cabine já nasce `delivered`.
- **Transições:** todas as transições válidas da tabela da seção 5, mais as inválidas, que não têm efeito. Incluem desfazer, alteração (`edited_at`) e cancelamento com motivo.
- **Permissões:**
  - a `cozinha` não lança nem cancela pedidos;
  - as rotas públicas não alteram nada;
  - o `/admin` só abre para `admin`.
- **Cardápio:** "Esgotado" e "nome do dia" valem só para o dia corrente.
- **Livewire:** fluxos de lançar, editar, marcar pronto pelo balcão e pela cozinha, e o anúncio do painel.
- **Broadcast:** com `Event::fake`, os eventos certos são disparados nos canais certos, e o payload público não vaza dados.
- **Antes de cada deploy:**
  - Pint e a suíte de testes passando;
  - um teste rápido no navegador com estação, painel e acompanhamento abertos ao mesmo tempo.

---

## 11. Riscos e validações iniciais

Estas validações vêm no começo da implementação:

1. **Estação em dois monitores:**
   - a janela única do Chrome cobrindo as duas telas;
   - o mapeamento do toque para o monitor certo (calibração de toque do Windows);
   - os botões da cozinha sem tirar o foco do balcão;
   - o roteamento das teclas `Numpad*`.
   - Teste com qualquer segundo monitor disponível, e depois com o monitor definitivo.
2. **Reverb atrás do nginx na VPS compartilhada:** proxy do WebSocket com HTTPS, serviço systemd e reinício automático.
3. **Som automático na TV** com o Fully Kiosk Browser no Google TV.
4. **DNS:** registro A de `comanda.mswebsolutions.site` e emissão do certificado.

---

## 12. Fase 2 (prévia, sem design detalhado)

**Totem de autoatendimento**, com o cliente pagando antes:
- **Tela:** tablet de 11–13" num pedestal com trava, rodando Fully Kiosk.
- **Maquininha integrada:** Mercado Pago Point Smart 2 em modo `SELF_SERVICE` (API de Orders, com notificações por webhook, que exigem HTTPS). A Getnet continua no balcão.
  - A integração direta com a Getnet exige o canal comercial ou de parceiros, ou um TEF. Fica como alternativa a avaliar.
- **Pix:** QR dinâmico na tela, via API.
- **Ticket impresso:** opcional, decidir na fase 2. Imprimir a partir da nuvem exige uma solução local.
- **NFC-e:** avaliar o envio dos pedidos pagos ao `nananotas`.
- **O que a fase 1 já prepara:**
  - campo `source` no pedido;
  - preços congelados nos itens;
  - contador de número à prova de concorrência;
  - "Esgotado" em tempo real;
  - fotos dos produtos.

---

## 13. Equipamentos da fase 1

Preços de referência pesquisados em nov/2026; `~` indica estimativa.

| Item | Recomendação | Preço |
|---|---|---|
| Balcão | PC existente com Chrome. Precisa de **2 saídas de vídeo** | — |
| Cozinha, opção toque | Monitor touch 15,6" **capacitivo (PCAP)** com **HDMI + USB + VESA** (ex.: Jetway JMT 330, K-Mex, Philips 15,6" touch). Evitar modelos só VGA e molduras adaptadoras | ~R$1.300–1.800 |
| Cozinha, opção teclado | Monitor comum de 19,5–21,5" com VESA + teclado numérico de silicone USB lavável | ~R$450–820 |
| Posição na cozinha | Na passagem entre a cozinha e o balcão, ao alcance das mãos, longe do fogão e da fritadeira. Suporte VESA | ~R$80–150 |
| Cabos | HDMI + USB até ~5 m. Acima disso, extensão USB ativa | ~R$50–150 |
| TV do salão | TV 43" com Google TV + suporte de parede + Fully Kiosk Browser (licença PLUS, ~€8, pagamento único) | ~R$1.500–2.200 |
| QR code | Adesivo ou display de acrílico no balcão | ~R$20–50 |
| Opcionais | Nobreak para o roteador e o PC; repetidor Wi-Fi; teclado compacto para a mãe | ~R$400–700 / ~R$150 / ~R$60–100 |

**Total estimado** (soma das faixas, sem os opcionais): ~R$3.000–4.400 com a opção de toque, ou ~R$2.150–3.400 com a opção de teclado.

**Antes de comprar:**
- conferir as saídas de vídeo do PC do balcão;
- medir a distância do PC até o ponto do monitor da cozinha;
- confirmar o padrão VESA do monitor e do suporte.

---

## 14. Pendências do usuário

Resolvidas em 2026-11-13:
- ~~Criar o DNS~~: `comanda.mswebsolutions.site` já resolve para `145.223.26.225`.
- ~~Escolher o remoto do git~~: `github.com/marcosserg12/nova_comanda` (vazio, acessível).
- ~~Lista de sucos~~: Limão R$4, Goiaba R$5, Manga R$6 (apêndice A).
- ~~Saídas de vídeo do PC~~: o PC do balcão tem duas.

Em aberto:
1. Cadastrar a chave de deploy da VPS no GitHub, na hora do primeiro deploy (o passo a passo será fornecido).
2. Decidir entre toque e teclado na cozinha. Pode ficar para a hora da compra, porque o software aceita os dois.

---

## Apêndice A: cardápio inicial (seed, a partir da placa da loja)

**Pratos** (destino: cozinha). Todos acompanham arroz, feijão, fritas, farofa e salada.

| Produto | Preço | Observação |
|---|---|---|
| Prato do dia | R$26,00 | Nome do dia (ex.: Strogonoff, Feijoada) |
| Prato Econômico | R$17,00 | Nome do dia (ex.: Almôndega com molho de tomate) |
| Frango ao molho | R$23,00 | |
| Fígado bovino | R$22,00 | |
| Omelete especial | R$22,00 | |
| Filé de frango | R$24,00 | |
| Filé suíno | R$22,00 | |
| Frango à milanesa | R$25,00 | |
| Parmegiana de frango | R$27,00 | |
| Filé de peixe | R$29,00 | |
| Carne de sol | R$32,00 | |
| Coxão mole | R$34,00 | |
| Parmegiana de carne | R$36,00 | |
| Filé mignon | R$40,00 | |
| Picanha bovina | R$38,00 | |

**Sucos** (destino: cozinha)

| Produto | Preço |
|---|---|
| Suco de limão | R$4,00 |
| Suco de goiaba | R$5,00 |
| Suco de manga | R$6,00 |

**Bebidas** (destino: cabine): cadastro opcional, só para controle.

**Observações rápidas**
- Pratos: Sem arroz · Sem feijão · Sem fritas · Sem farofa · Sem salada · C/ Ovo · C/ Cebola
- Sucos: Sem açúcar

**Usuários iniciais:** `admin` (desenvolvedor/pai) e `balcao` (mãe). As senhas são definidas na instalação.
