# Instruções para o Claude neste projeto

## Idioma (obrigatório)
- Escreva SEMPRE em português do Brasil: todas as mensagens para o usuário, perguntas, resumos, legendas de arquivos, mensagens de commit e textos do site e do sistema.
- Nunca troque para o inglês, nem em mensagens longas, técnicas ou de status.
- Linguagem simples, frases curtas, sem travessão.

## Projeto
- Site público e sistema (painel) da Farias Araújo Construções Pré Moldadas, Brasília.
- Site: `site/` (HTML, CSS e JS puros). Sistema: PHP 8 + SQLite, banco em `fa-dados/` (fora do repositório).
- O repositório é público: nunca envie dados de funcionários, valores de folha ou o banco para o Git. Cópias de segurança ficam em `backup-local/` (ignorada).
- Folha de pagamento a cada 15 dias, contada a partir de 08/09/2026 (FA_CICLO_INICIO).
- Diárias do dia a dia entram pela tela "Colar da planilha" (`site/painel/planilha.php`).
