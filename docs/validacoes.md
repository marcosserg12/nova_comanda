# Validações de hardware e infraestrutura

## 1. Estação em dois monitores (Task 2, data: 2026-11-16)

| # | Verificação | Resultado |
|---|---|---|
| 1 | Digitando no campo "nome", tocar/clicar nos botões da cozinha não tira o foco (log mostra `foco atual: name`) e a digitação continua no campo | OK |
| 2 | Dígitos do teclado numérico NÃO aparecem no campo "nome"; aparecem como "Nº" na cozinha | OK |
| 3 | Enter do teclado numérico não envia o formulário do balcão; o Enter principal envia | OK |
| 4 | Toque no monitor touch cai na metade da cozinha (se não: Painel de Controle → Configurações do Tablet PC → Configurar → Entrada por toque) | sem hardware disponível — repetir na Task 20 |
| 5 | Janela única cobre os dois monitores sem "grudar" em um só | sem hardware disponível — repetir na Task 20 |

Conclusão: as verificações 1–3 foram validadas por eventos reais de entrada no Chromium automatizado (Playwright, com `hasTouch: true`, cliques e `tap()`); botões da cozinha e teclado numérico não roubam foco nem teclas do campo do balcão, então o plano A (balcão e cozinha na mesma janela) segue válido. As verificações 4 e 5 dependem de hardware (monitor touch e dois monitores) e ficam pendentes para a Task 20.

## 2. Reverb atrás do nginx na VPS (Task 16, data: 2026-11-16)

| Verificação | Resultado |
|---|---|
| `https://comanda.mswebsolutions.site/up` responde 200 com HTTPS (HTTP redireciona 301 para HTTPS) | OK |
| Handshake WebSocket em `/app/{key}` responde 101 (direto no Reverb e pelo nginx) | OK |
| `systemctl is-active comanda-reverb` = active, ouvindo só em 127.0.0.1:8080 | OK |
| Pedido lançado aparece na cozinha e no painel sem recarregar (~2,2 s do clique em ENVIAR, incluindo o envio) | OK |
| PRONTO na cozinha → anúncio "PEDIDO PRONTO" na TV em ~0,9 s; card sai da cozinha após a janela de desfazer | OK |
| `/.env` bloqueado (403); log de produção sem avisos após o teste | OK |

Observações:
- Teste feito com Chromium automatizado (Playwright) contra a produção, sem erros no console. O pedido de teste 001 foi cancelado em seguida ("Teste de implantação").
- O comando de handshake precisa de uma `Sec-WebSocket-Key` válida (16 bytes em base64, ex.: `dGhlIHNhbXBsZSBub25jZQ==`); com uma chave inválida o Reverb responde 400.
- O MySQL da VPS usa `validate_password` MEDIUM: a senha do usuário `comanda` precisa de maiúscula, número e caractere especial.

## 4. DNS
`comanda.mswebsolutions.site` → `145.223.26.225` (confirmado em 2026-11-13; certificado Let's Encrypt emitido em 2026-11-16).

## 5. Redesenho das telas (Task 8 do plano do redesenho, data: 2026-11-16)

Varredura com `node tests/Browser/screenshots.cjs` (Chromium automatizado, servidor e Reverb locais). Em cada tamanho: sem rolagem horizontal (`scrollWidth <= innerWidth`) e elementos-chave inteiros na tela (botão Enviar e total do balcão, último prato alcançável, botões PRONTO dos cartões visíveis e rodapé "+N pedidos abaixo" da cozinha, blocos da tela de início, números e QR da TV, campo "Seu número" do celular). Capturas em `storage/app/screenshots/` (não versionadas).

| Tela e tamanho | Resultado |
|---|---|
| Início 1366×768 | OK |
| Login 1366×768 | OK |
| Balcão 1366×768 | OK |
| Balcão 1280×800 (tablet deitado) | OK |
| Balcão 800×1280 (tablet em pé) | OK |
| Cozinha 1366×768 | OK |
| Cozinha: destaque na borda e aviso no rodapé 1366×768 | OK |
| Estação 2×1366×768 | OK |
| Painel da TV 1920×1080 | OK |
| Celular 390×844 | OK |

Correções feitas durante a varredura:
- nenhuma (a varredura passou de primeira; os ajustes finais A1–A6 foram feitos antes, no commit "ajustes finais do redesenho").

Pendente na loja (dono): leitura da cozinha a ~2 m, toque no tablet (se houver) e "Detectar monitores" com os dois monitores reais.

## 1b. Estação com o hardware definitivo (Task 20, data: AAAA-MM-DD)

| Verificação | Resultado |
|---|---|
| Atalho abre a estação cobrindo os dois monitores | OK / FALHOU |
| Botão "Detectar monitores" acerta a divisão entre balcão e cozinha | OK / FALHOU |
| Toque (ou teclado numérico) da cozinha não atrapalha a digitação do balcão | OK / FALHOU |
| Pedido novo aparece com NOVO (e rodapé amarelo quando está mais abaixo) e dá para ver do fogão | OK / FALHOU |

## 3. Som automático na TV (Fully Kiosk) (Task 20, data: AAAA-MM-DD)

| Verificação | Resultado |
|---|---|
| Painel abre sozinho ao ligar a TV | OK / FALHOU |
| Anúncio "PEDIDO PRONTO" toca som sem tocar na TV | OK / FALHOU |
| Após cair e voltar a internet, o painel volta a atualizar sozinho | OK / FALHOU |

Qualquer FALHOU vira item de acompanhamento para o dono. Não bloqueia o fechamento da fase 1 se houver um jeito de contornar, como tocar em "Toque para começar" uma vez por dia na TV.
