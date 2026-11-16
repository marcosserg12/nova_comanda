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
