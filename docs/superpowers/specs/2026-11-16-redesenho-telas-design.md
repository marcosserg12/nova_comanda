# Nana Comanda — Redesenho das telas (design)

**Data:** 2026-11-16
**Status:** aprovado visualmente com o dono (maquetes no companheiro visual); aguardando revisão deste documento.
**Base:** complementa `docs/superpowers/specs/2026-11-13-comanda-fase1-design.md`. Regras de negócio, dados, tempo real e segurança daquele documento continuam valendo. Este documento substitui apenas o **visual e a organização das telas** (§6 de lá) e acrescenta a **tela de início**.

## 1. Por que

O dono achou as telas feias e mal ajustadas ao monitor da loja. A auditoria em 1366×768 confirmou:

- **Estação:** larguras fixas pensadas para monitor de 1920 px. No monitor de 1366, o balcão invade o monitor da cozinha e os cartões da cozinha ficam cortados.
- **Cozinha:** só cabem 3–4 pedidos, e os cabeçalhos vermelhos enormes desperdiçam espaço.
- **Balcão:** colunas espremidas, a lista de pratos corta embaixo e o botão "Marcar esgotado" quebra a linha.
- **Visual:** genérico e sem hierarquia.

Além disso, hoje é preciso digitar o endereço de cada tela (/estacao, /cozinha, /painel…).

## 2. Decisões tomadas com o dono

| # | Decisão |
|---|---|
| D1 | O balcão é um monitor de **18,5" (≈1366×768)**; no futuro pode ser um **tablet**. Base de projeto: 1366×768, com versões para tablet deitado (≈1280×800) e em pé (≈800×1280). |
| D2 | Aviso de pedido novo na cozinha **só visual**, porque a cozinha não ouve o som. Ficou **apenas o cartão piscando com "NOVO"**. A tela amarela de 3 s foi recusada: atrapalharia. |
| D3 | Estilo **"Lanchonete marcante"**: faixa vermelha no topo, fundo creme, contornos pretos, sombras "duras", letras grossas, amarelo para seleção e destaque. |
| D4 | **Tela de início** para escolher onde entrar, com a opção **"Abrir sempre esta tela neste aparelho"**. Botão **"Telas"** no topo de todas as telas. |
| D5 | Balcão em **três colunas**: pratos, pedido em montagem e lista "Na cozinha" sempre à vista. |
| D6 | TV, celular do cliente e login no estilo novo, com a mesma lógica de hoje. A TV **mantém** o anúncio em tela cheia ao ficar pronto, porque é para o cliente. |

## 3. Sistema visual

Tokens em `resources/css/app.css` (`@theme` do Tailwind 4). Os nomes antigos `nana-*` são substituídos:

| Token | Valor | Uso |
|---|---|---|
| `--color-nana-red` | `#D62828` | faixa do topo, botão principal ("Enviar"), coluna "Pronto" da TV |
| `--color-nana-cream` | `#FFF2D9` | fundo das telas |
| `--color-nana-ink` | `#161616` | texto, contornos, sombras |
| `--color-nana-yellow` | `#FFB703` | seleção, categoria ativa, "NOVO", aviso da TV |
| `--color-nana-green` | `#1F8A50` | "PRONTO" (cozinha), confirmações |
| `--color-nana-orange` | `#E8590C` | tempo de espera em alerta |
| `--color-nana-muted` | `#6B6257` | textos secundários |
| `--color-nana-line` | `#E7D9C2` | divisórias internas leves |

- **Fontes:** `Archivo Black` para títulos, números de pedido e preços; `Archivo` (500–800) para o resto. Ficam **empacotadas no projeto** (pacotes `@fontsource/archivo` e `@fontsource/archivo-black` via Vite), sem depender do Google Fonts. Assim as telas não mudam de aparência se a internet da loja oscilar.
- **Superfícies:** cartões brancos com contorno `2px` preto, raio 8–10 px e sombra dura `4px 4px 0 #161616`. Elemento desabilitado ou esgotado: opacidade 45% e sem sombra.
- **Componentes Blade reutilizáveis:**
  - `x-ui.topbar`: faixa vermelha com logo, botão "Telas", abas opcionais e indicador Online/Sem conexão.
  - `x-ui.button` (variantes primary, dark, ghost).
  - `x-ui.chip`.
  - `x-ui.badge`.
  - `x-ui.card`.
- **Toque:** alvos de no mínimo 44 px (48 px abaixo de 1024 px de largura) e foco visível (anel amarelo de 3 px).
- **Animações:** `prefers-reduced-motion` desliga a pulsação do "NOVO", que vira só contorno fixo.

## 4. Tela de início (nova)

**Rota:** `GET /` (`home`). Hoje ela redireciona por perfil; passa a **mostrar a tela de início** (`pages/inicio.blade.php`).

**Blocos** (grade 3×2 em ≥1024 px; 2 colunas no tablet em pé; 1 coluna no celular):

| Bloco | Destino | Quem vê |
|---|---|---|
| Estação ("PC do balcão") | `/estacao` | admin, balcao |
| Balcão | `/balcao` | admin, balcao |
| Cozinha | `/cozinha` | admin, balcao, cozinha |
| Painel da TV | `/painel` | todos, inclusive sem login |
| Acompanhar | `/acompanhar` | todos, inclusive sem login |
| Administração ("Só admin") | `/admin` | admin |

- **Sem login:** aparecem Painel da TV, Acompanhar e um bloco **"Entrar"** (login). Depois do login, volta para `/`, ou para a tela que a pessoa tentou abrir (`intended`, como já é hoje).
- **Filtro por perfil:** a lista é montada no servidor com os papéis/gates existentes. Nada muda na proteção das rotas: o bloco escondido continua bloqueado na rota.
- **"Abrir sempre esta tela neste aparelho":**
  - A escolha fica em `localStorage` (`nana.home`, com o nome da rota).
  - Ao abrir `/`, se houver escolha salva e ela estiver na lista permitida, o navegador vai direto para ela.
  - `/?escolher=1` ignora a escolha salva. É assim que o botão "Telas" leva de volta à tela de início, que mostra "Esquecer a escolha deste aparelho".
  - Se a escolha salva não for permitida (outro usuário, ou sessão expirada para tela protegida), mostra a tela de início normalmente. Se a sessão expirou, o bloco protegido leva ao login.
- **Botão "Telas":** em todas as telas, no topo. Abre um menu com as telas permitidas e "Tela de início". Na TV fica discreto (canto inferior, só ícone) para não poluir a tela do cliente.

## 5. Balcão (`/balcao` e metade esquerda da Estação)

**Estrutura** por largura disponível. Na Estação, a largura é a do monitor esquerdo.

| Largura | Organização |
|---|---|
| ≥ 1280 px | **3 colunas**: pratos (grade de 3 colunas) · pedido (~27%) · "Na cozinha" (~17%) |
| 1024–1279 px (tablet deitado) | 2 colunas: pratos · pedido. "Na cozinha" vira **aba** no topo, com contador. |
| < 1024 px (tablet em pé) | 1 coluna de pratos (grade de 2). O pedido vira **barra fixa embaixo** ("Maria · 3 itens · R$ 38,00 →") que abre o pedido completo por cima. |

**Topo:** abas **Novo pedido · Pedidos de hoje** (mais "Na cozinha" quando ela vira aba) e Online.

**Coluna de pratos:**
- Categorias em chips, com a ativa em amarelo, e busca.
- O botão "Marcar esgotado" vai para um menu "⋯" ao lado da busca, para não quebrar a linha. O modo esgotado continua igual.
- Cada prato é um cartão com nome, preço em `Archivo Black` e o nome do prato do dia em vermelho.
- Prato no pedido: contorno amarelo e bolinha vermelha com a quantidade.
- Esgotado: apagado, com "Esgotado hoje".
- A grade rola dentro da coluna, sem cortar o último prato.

**Coluna do pedido:**
- Título "Pedido" com o número que será usado.
- Nome do cliente com borda vermelha (foco inicial).
- Seletor **"Comer aqui | Para viagem"** no lugar da caixinha "Para viagem". É o mesmo campo `takeaway`.
- Linhas com − / + e observação. Os chips de observação rápida continuam **por linha**, como hoje, mostrados sob a linha.
- Total e botão vermelho **"Enviar para a cozinha"** (Enter continua enviando).
- O pedido rola internamente e o total e o botão ficam sempre visíveis.

**Coluna "Na cozinha":** lista compacta com número, nome e botão verde "Pronto", mais o desfazer que já existe.

**Comportamento preservado:**
- rascunho que sobrevive ao F5;
- pergunta do prato do dia;
- busca com Enter;
- edição de pedido (com a trava de não sobrescrever);
- cancelamento com motivo;
- tempo de espera em "Pedidos de hoje".

## 6. Cozinha (`/cozinha` e metade direita da Estação)

- **Grade automática:** colunas com largura mínima de ~300 px (4 colunas em 1366 px), do pedido mais antigo para o mais novo. Os cartões têm a altura do conteúdo, sem esticar.
- **Cartão:**
  - número grande e tempo de espera;
  - nome;
  - etiqueta **VIAGEM** quando for para viagem;
  - itens com quantidade e observação em vermelho;
  - botão verde **PRONTO** ocupando a base.
- **Atraso** (valores em `config/comanda.php`):
  - `kitchen_warn_minutes = 15`: o tempo fica laranja;
  - `kitchen_late_minutes = 25`: o cartão fica rosado e o tempo vai para uma etiqueta vermelha.
- **Pedido novo (D2):**
  - Quando chega um pedido (evento `created`), o cartão recebe a etiqueta **"NOVO"** e um contorno amarelo pulsando.
  - O destaque some quando alguém toca no cartão (fora do botão PRONTO) ou depois de **60 s**.
  - Pedido editado (`updated`) recebe o mesmo destaque, com a etiqueta **"ALTERADO"**.
  - **Sem som e sem tela cheia.** O bip da cozinha é removido.
- **Muitos pedidos:** quando a grade passa da altura da tela, aparece no rodapé **"+N pedidos abaixo ↓"**, e a grade rola.
- **Rodapé:** dica do teclado numérico ("digite 14 e Enter para marcar pronto"). O teclado numérico, o desfazer e a regra de não roubar o foco do balcão continuam como hoje.
- **"Toque para começar":** sai da cozinha e da Estação. Ele existia para liberar o som, que deixou de existir ali. A tela pede para não apagar (Wake Lock) ao abrir e de novo quando volta a ficar visível.

## 7. Estação (`/estacao`)

- Continua sendo **uma janela do Chrome cobrindo os dois monitores**: balcão à esquerda, cozinha à direita.
- **Largura do monitor esquerdo:**
  1. Se o Chrome permitir o recurso de gerenciamento de janelas (`window.getScreenDetails()`, pedido uma única vez pelo botão **"Detectar monitores"** da Estação), usa a largura real do monitor esquerdo e guarda no aparelho (`localStorage`).
  2. Senão, usa a configuração do admin (`station_left_width`), cujo padrão passa de 1920 para **1366**.
- **Só um monitor** (janela com menos de largura esquerda + 600 px): a Estação mostra só o balcão de três colunas, que já tem "Na cozinha", e um aviso discreto "Cozinha no 2º monitor não encontrada".

## 8. Painel da TV (`/painel`)

- **Esquerda, "Preparando":** contador e grade de números grandes com o primeiro nome, até **12** visíveis. Os mais antigos aparecem primeiro e o excedente vira "+N". Isso resolve o estouro da grade já anotado para a revisão final.
- **Direita, coluna vermelha "Pronto!":** número e nome em cartões grandes, QR "Acompanhe pelo celular" e relógio.
- **Anúncio ao ficar pronto:** tela cheia amarela com "PEDIDO PRONTO / 012 / João / Retire no balcão" por ~8 s, com o som atual e uma fila quando há vários. Mantém o "Toque para começar" da TV, que libera o som.

## 9. Acompanhar no celular (`/acompanhar`)

- Faixa vermelha com "Nana Lanches · Acompanhe seu pedido" e campo **"Seu número"**, guardado no aparelho como hoje.
- Cartão do meu pedido em dois estados:
  - **"Preparando…"**: branco;
  - **"PRONTO! Retire no balcão"**: amarelo, e o celular vibra como hoje.
- Embaixo, as listas curtas de prontos e em preparo.

## 10. Login

Fundo vermelho e cartão creme com contorno e sombra dura: "Nana Lanches", usuário, senha, "Manter conectado neste aparelho" e o botão escuro "Entrar". O comportamento não muda (limite de tentativas, lembrar-me).

## 11. Fora do escopo

- Visual do painel administrativo (Filament): continua como está.
- Novas regras de negócio, novos campos ou mudanças no tempo real.
- Mudanças no som da TV.

## 12. Testes e verificação

- **Pest (feature):**
  - tela de início: blocos por perfil (admin, balcao, cozinha, visitante), rotas dos blocos, `/?escolher=1`, login voltando para `intended` ou `/`;
  - padrão de `station_left_width` igual a 1366;
  - limites de atraso vindos da configuração;
  - etiqueta VIAGEM no cartão da cozinha;
  - testes existentes de marcação ajustados ao HTML novo, sem perder as asserções de comportamento.
- **Navegador automatizado (Playwright), com servidor e Reverb reais:**
  - Capturas comparadas com as maquetes aprovadas, nos tamanhos:
    - 1366×768: início, balcão, cozinha e login;
    - 2×1366×768: Estação;
    - 1280×800 e 800×1280: balcão no tablet;
    - 1920×1080: TV;
    - 390×844: celular.
  - Comportamentos a verificar:
    - "NOVO" aparece ao chegar pedido e some ao tocar ou depois de 60 s;
    - "abrir sempre neste aparelho" redireciona e `/?escolher=1` ignora a escolha;
    - "+N pedidos abaixo" com 10 pedidos;
    - foco do balcão preservado ao tocar na cozinha;
    - nenhum corte de conteúdo em nenhum tamanho.
- **Na loja (dono):**
  - leitura da cozinha a ~2 m de distância;
  - toque no tablet, se houver;
  - "Detectar monitores" com os dois monitores reais.

## 13. Entrega

- Deploy em produção só depois da revisão de cada etapa, como no resto do projeto.
- O guia de operação (Tarefa 20 do plano da fase 1) é escrito **depois** deste redesenho, para descrever as telas novas.
