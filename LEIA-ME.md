# Farias Araújo · site e sistema

## O que tem aqui

- `site/` é a pasta que vai para o `public_html` da HostGator. Tudo dentro dela vai para o ar.
  - `index.html`: o site público.
  - `acompanhar.php`: a página onde o cliente digita o código e vê a obra.
  - `painel/`: o sistema da equipe:
    - Comercial: pedidos do site, clientes, orçamentos em PDF.
    - Obra: obras com etapas, fotos e custo real (mão de obra + despesas), diárias do dia, despesas, galeria.
    - Equipe: funcionários com valor da diária e Pix, vales, fechamento quinzenal (1 a 15 e 16 ao fim do mês) com recibos.
    - Modo claro para usar no sol (botão no menu, fica lembrado no aparelho).
  - `api/`: recebe os pedidos de orçamento do site e entrega as fotos da galeria.
  - `inc/`: configuração e código compartilhado (bloqueado para acesso direto).
  - `uploads/`: fotos enviadas pelo painel (nenhum script roda aqui).
- `materiais/`: logo, cartão e QR originais.
- `PACOTE-DE-DESIGN.md`: cada decisão de design, todo o texto do site e o estado do projeto.
- `fa-dados/` (criada sozinha, fora do ar): o banco SQLite. Na HostGator ela fica ao lado de `public_html`, nunca dentro.

## Primeiro acesso ao painel

Abra `/painel/`. Na primeira vez aparece "Primeiro acesso" para criar a senha de Kauan (usuário `kauan`) e de David (usuário `david`). Depois disso a tela some.

## Para ligar o vídeo do topo

Depois de gerar e processar o vídeo (receitas no pacote de design), no `index.html`, no elemento `.palco`:

```html
<div class="palco" data-video="assets/hero-scrub.mp4" data-poster="assets/hero-poster.jpg" data-bytes="TAMANHO_EM_BYTES">
```

O desenho da casa continua como versão do celular e como reserva se o vídeo falhar.

## Testar no computador

```bash
php -S 127.0.0.1:8090 -t site
```

Requisitos no servidor: PHP 8.1 ou mais novo com PDO SQLite e GD (padrão na HostGator).

## Como funciona o fechamento

O período segue o ciclo de vocês: **15 dias corridos** a partir de 08/09/2026 (08/09 a 22/09, 23/09 a 07/10...). Dá para mudar em `site/inc/config.php` (`FA_CICLO_INICIO` e `FA_CICLO_DIAS`) ou ajustar as datas de um fechamento na própria tela.

A folha de 08/09 a 22/09/2026 (enviada em PDF) pode ser importada em **Fechamento → Importar a folha**, ou abrindo `/painel/importar.php`.

1. Todo dia, em **Diárias**, marque cada funcionário: Não veio, Meia ou Diária (e a obra, e algum extra do dia).
2. Adiantamentos entram como **vale** (em Funcionários ou no Fechamento) e são descontados na quinzena da data.
3. Descontos parcelados (como o Prisma) ficam na ficha do funcionário. A próxima parcela pendente entra sozinha no fechamento, e dá para desmarcar.
4. No fim do período, em **Fechamento**, confira e toque em **Fechar a folha**. Os valores ficam travados.
5. **Relatório completo (PDF)** gera o relatório geral e a folha individual de cada um, no modelo de vocês.
6. Marque cada um como pago, mande o resumo no WhatsApp e imprima o recibo.
7. Se precisar corrigir algo, **Reabrir fechamento** destrava tudo e devolve as parcelas para pendente.

O valor da diária é guardado em cada dia marcado. Se a diária de alguém aumentar, os dias antigos continuam com o valor antigo.
