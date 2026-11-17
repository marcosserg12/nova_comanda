# Nana Comanda: guia de operação da loja

Endereço do sistema: **https://comanda.mswebsolutions.site**

## Telas

| Tela | Endereço | Onde fica |
|---|---|---|
| Tela de início | `/` | escolhe onde entrar; cada pessoa vê só o que pode usar |
| Balcão + Cozinha | `/estacao` | PC do balcão, dois monitores |
| Só balcão | `/balcao` | tablet ou outro computador |
| Só cozinha | `/cozinha` | aparelho próprio na cozinha (plano B) |
| TV do salão | `/painel` | TV com Google TV |
| Celular do cliente | `/acompanhar` | QR code no balcão |
| Administração | `/admin` | seu pai ou você |

**Botão "Telas":** fica no topo de todas as telas. Ao tocar, abre uma lista com as telas que você pode abrir e a opção **"Tela de início"**. **Se estiver perdido, toque em "Telas".**

**Abrir sempre a mesma tela:** na tela de início existe a caixinha **"Abrir sempre esta tela neste aparelho"**. Marque e escolha a tela: dali em diante, o aparelho abre direto nela. É o que o PC da TV e o da cozinha devem fazer. Para desfazer: botão **"Telas"** → **"Tela de início"** → **"Esquecer a escolha deste aparelho"**.

## Montagem do PC do balcão (uma vez)

1. Ligue os dois monitores. Em Windows → Configurações → Sistema → Tela, deixe-os lado a lado (balcão à esquerda, cozinha à direita), na mesma altura. Anote a resolução de cada um.
2. Se a cozinha usar monitor touch, vá em Painel de Controle → Configurações do Tablet PC → **Configurar** → Entrada por toque, e toque no monitor da cozinha quando ele pedir.
3. Crie um atalho na área de trabalho com este destino. A largura da janela (`--window-size`) é a **soma das larguras dos dois monitores**, e a altura é a de um monitor. Exemplo com dois monitores de 18,5" (1366×768 cada): `2732,768`.
   ```
   "C:\Program Files\Google\Chrome\Application\chrome.exe" --user-data-dir="C:\nana-estacao" --app="https://comanda.mswebsolutions.site/estacao" --window-position=0,0 --window-size=2732,768 --autoplay-policy=no-user-gesture-required
   ```
4. Abra o atalho e, na tela Balcão + Cozinha, clique em **"Detectar monitores"** uma vez. O Chrome pergunta se pode "gerenciar janelas": clique em **Permitir**. Se não funcionar, entre em `/admin` → Configurações e ajuste a **largura do monitor do balcão** (o padrão é 1366).
5. Se houver só um monitor ligado, a tela Balcão + Cozinha mostra apenas o balcão (com a coluna "Na cozinha") e um aviso pequeno: "Cozinha no 2º monitor não encontrada". Confira o cabo do segundo monitor.
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
3. Se aparecer "Toque para começar" na TV, o som automático não está liberado: revise o item Autoplay Audio. (Só a TV tem esse aviso; o balcão e a cozinha não têm.) Enquanto isso não for resolvido, toque uma vez na tela da TV por dia para liberar o som.
4. A TV mostra até 12 pedidos em "Preparando"; se houver mais, aparece "+N" (por exemplo "+3"). A coluna vermelha mostra os 4 pedidos prontos mais recentes.

## QR code do balcão

Imprima o QR que aparece no canto da TV, ou gere um para `https://comanda.mswebsolutions.site/acompanhar`, com o texto "Acompanhe seu pedido".

## Rotina do dia

- **Ao abrir:** ligue o PC e a TV. Nada mais para tocar: o balcão e a cozinha já ficam prontos.
- **Fazer um pedido:** digite o nome do cliente, toque nos pratos, escolha **Comer aqui** ou **Para viagem** (logo abaixo do nome) e toque em **"Enviar para a cozinha"** (ou tecle Enter).
- **Prato do dia:** ao tocar no "Prato do dia" pela primeira vez, o sistema pergunta o prato de hoje. Para trocar, use o ✎ no botão.
- **Acabou um prato:** toque no botão **"⋯"** ao lado da busca → **"Marcar esgotado"** e toque no prato que acabou. Quando terminar, toque em **"Concluir esgotados"**. No dia seguinte o prato volta sozinho.
- **Errou um pedido:** em "Pedidos de hoje", use "Editar" enquanto estiver em preparo, ou "Cancelar" com o motivo.
- **Cobrança:** em "Pedidos de hoje", procure pelo número ou pelo nome. O total aparece na linha.
- **Mudar preço:** no `/admin` → Produtos, edite o preço direto na lista.

### Na cozinha

- **Pedido novo:** aparece com a etiqueta **"NOVO"** e o contorno piscando. Pedido que foi editado aparece com **"ALTERADO"**. O piscar para quando alguém toca no pedido, ou sozinho depois de 1 minuto.
- **Pedido lá embaixo na tela:** se o pedido novo chegou numa parte que não aparece, a barra de baixo fica **amarela**, com "NOVO PEDIDO abaixo ↓ — toque para ver" (ou "PEDIDO ALTERADO abaixo ↓ — toque para ver", se foi um pedido editado). Toque nela para rolar até o pedido.
- **Cores do tempo:** o tempo fica **laranja** a partir de 15 minutos de espera, e o cartão fica **vermelho** a partir de 25 minutos.
- **A cozinha não tem som.** Olhe para a tela: o pedido novo aparece com "NOVO".
- **Marcar pronto:** toque no botão verde **PRONTO** do pedido. Ou pelo **teclado numérico** (o bloco de números do lado direito do teclado): digite o número do pedido nesses números e tecle o **Enter do teclado numérico**. Errou? Tecle o **"−" do teclado numérico** para desfazer. (Os números de cima do teclado e o Enter grande não marcam pronto, para não atrapalhar quem digita no balcão.)

## Se algo der errado

- **"Sem conexão" em vermelho:** a internet caiu (quando está tudo bem, aparece **"Online"** em verde). Use o 4G de um celular no PC e na TV. Se demorar, volte para o papel por um tempo. Nenhum pedido já enviado se perde.
- **Pedido não apareceu na cozinha:** espere até 20 segundos (a tela confere sozinha) ou aperte F5.
- **Esqueceu a senha:** o admin troca em `/admin` → Usuários.
- **Backups:** o sistema guarda uma cópia do banco todos os dias às 03:00, por 14 dias.

## Para o desenvolvedor

- Atualizar o sistema: `ssh root@145.223.26.225 'bash /var/www/comanda/deploy/deploy.sh'`
- Atualize fora do horário de atendimento; depois, aperte F5 na TV e no PC do balcão (balcão + cozinha) para carregarem a versão nova.
- O arquivo nginx no servidor difere de `deploy/nginx/*.conf` porque o certbot acrescenta o bloco HTTPS no próprio servidor; não sobrescreva o arquivo do servidor com o do repositório.
- Backups em `/var/backups/comanda` (pasta só do sistema, `comanda-AAAA-MM-DD_HHMMSS.sql.gz`).
