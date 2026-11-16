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
