# Nana Comanda: guia de operação da loja

Endereço do sistema: **https://comanda.mswebsolutions.site**

## Telas

| Tela | Endereço | Onde fica |
|---|---|---|
| Tela de início | `/` | escolhe onde entrar; cada pessoa vê só o que pode usar |
| Estação (balcão + cozinha) | `/estacao` | PC do balcão, dois monitores |
| Só balcão | `/balcao` | qualquer computador |
| Só cozinha | `/cozinha` | aparelho próprio na cozinha (plano B) |
| TV do salão | `/painel` | TV com Google TV |
| Celular do cliente | `/acompanhar` | QR code no balcão |
| Administração | `/admin` | seu pai ou você |

**Botão "Telas":** fica no topo de todas as telas. Ao tocar, volta para a tela de início (ou mostra a lista das telas que você pode abrir).

**Abrir sempre a mesma tela:** na tela de início existe a caixinha **"Abrir sempre esta tela neste aparelho"**. Marque e escolha a tela: dali em diante, o aparelho abre direto nela. É o que o PC da TV e o da cozinha devem fazer. Para desfazer: botão **"Telas"** → **"Tela de início"** → **"Esquecer a escolha deste aparelho"**.

## Montagem do PC do balcão (uma vez)

1. Ligue os dois monitores. Em Windows → Configurações → Sistema → Tela, deixe-os lado a lado (balcão à esquerda, cozinha à direita), na mesma altura. Anote a resolução de cada um.
2. Se a cozinha usar monitor touch, vá em Painel de Controle → Configurações do Tablet PC → **Configurar** → Entrada por toque, e toque no monitor da cozinha quando ele pedir.
3. Crie um atalho na área de trabalho com este destino. A largura da janela (`--window-size`) é a **soma das larguras dos dois monitores**, e a altura é a de um monitor. Exemplo com dois monitores de 18,5" (1366×768 cada): `2732,768`.
   ```
   "C:\Program Files\Google\Chrome\Application\chrome.exe" --user-data-dir="C:\nana-estacao" --app="https://comanda.mswebsolutions.site/estacao" --window-position=0,0 --window-size=2732,768 --autoplay-policy=no-user-gesture-required
   ```
4. Abra o atalho e, na Estação, clique em **"Detectar monitores"** uma vez. O Chrome pergunta se pode "gerenciar janelas": clique em **Permitir**. Se não funcionar, entre em `/admin` → Configurações e ajuste a **largura do monitor do balcão** (o padrão é 1366).
5. Se houver só um monitor ligado, a Estação mostra apenas o balcão (com a coluna "Na cozinha") e um aviso pequeno: "Cozinha no 2º monitor não encontrada". Confira o cabo do segundo monitor.
6. Para abrir sozinho ao ligar: tecle Windows+R, digite `shell:startup` e copie o atalho para essa pasta.
7. Em Windows → Energia, deixe a tela e o computador **nunca** desligarem.
8. Faça login como `balcao` e marque **Manter conectado neste aparelho**.

## TV do salão (Google TV + Fully Kiosk Browser)

1. Instale o **Fully Kiosk Browser** (APK de fully-kiosk.com) e compre a licença PLUS (~€8, pagamento único).
2. Configure:
   - Start URL: `https://comanda.mswebsolutions.site/painel`;
   - Web Content Settings → **Autoplay Audio** ligado;
   - Device Management → **Keep Screen On** ligado e **Launch on Boot** ligado;
   - Web Auto Reload → recarregar ao reconectar a rede.
3. Se aparecer "Toque para começar" na TV, o som automático não está liberado: revise o item Autoplay Audio. (Só a TV tem esse aviso; a Estação e a cozinha não têm.) Enquanto isso não for resolvido, toque uma vez na tela da TV por dia para liberar o som.
4. A TV mostra até 12 pedidos em "Preparando"; se houver mais, aparece "+N" (por exemplo "+3"). Os pedidos prontos continuam todos na coluna vermelha.

## QR code do balcão

Imprima o QR que aparece no canto da TV, ou gere um para `https://comanda.mswebsolutions.site/acompanhar`, com o texto "Acompanhe seu pedido".

## Rotina do dia

- **Ao abrir:** ligue o PC e a TV. Nada mais para tocar: a Estação e a cozinha já ficam prontas.
- **Fazer um pedido:** digite o nome do cliente, toque nos pratos, escolha **Comer aqui** ou **Para viagem** (logo abaixo do nome) e toque em **"Enviar para a cozinha"** (ou tecle Enter).
- **Prato do dia:** ao tocar no "Prato do dia" pela primeira vez, o sistema pergunta o prato de hoje. Para trocar, use o ✎ no botão.
- **Acabou um prato:** toque no botão **"⋯"** ao lado da busca → **"Marcar esgotado"** e toque no prato que acabou. Quando terminar, toque em **"Concluir"**. No dia seguinte o prato volta sozinho.
- **Errou um pedido:** em "Pedidos de hoje", use "Editar" enquanto estiver em preparo, ou "Cancelar" com o motivo.
- **Cobrança:** em "Pedidos de hoje", procure pelo número ou pelo nome. O total aparece na linha.
- **Mudar preço:** no `/admin` → Produtos, edite o preço direto na lista.

### Na cozinha

- **Pedido novo:** aparece com a etiqueta **"NOVO"** e o contorno piscando. Pedido que foi editado aparece com **"ALTERADO"**. O piscar para quando alguém toca no pedido, ou sozinho depois de 1 minuto.
- **Pedido lá embaixo na tela:** se o pedido novo chegou numa parte que não aparece, a barra de baixo fica **amarela**, com "NOVO PEDIDO abaixo ↓ — toque para ver". Toque nela para rolar até o pedido.
- **Cores do tempo:** o tempo fica **laranja** a partir de 15 minutos de espera, e o cartão fica **vermelho** a partir de 25 minutos.
- **A cozinha não tem som.** Olhe para a tela: o pedido novo aparece com "NOVO".
- **Marcar pronto:** toque no botão verde **PRONTO** do pedido. Com o teclado numérico: digite o número do pedido e tecle Enter. Errou? Tecle **"−"** para desfazer.

## Se algo der errado

- **"Sem conexão" em vermelho:** a internet caiu (quando está tudo bem, aparece **"Online"** em verde). Use o 4G de um celular no PC e na TV. Se demorar, volte para o papel por um tempo. Nenhum pedido já enviado se perde.
- **Pedido não apareceu na cozinha:** espere até 20 segundos (a tela confere sozinha) ou aperte F5.
- **Esqueceu a senha:** o admin troca em `/admin` → Usuários.
- **Atualizar o sistema (desenvolvedor):** `ssh root@145.223.26.225 'bash /var/www/comanda/deploy/deploy.sh'`
- **Backups:** diários às 03:00 em `/var/backups/comanda`, guardados por 14 dias.
