# Pacote de Design: Farias Araújo Construções Pré Moldadas

Nível 1 (jornada única de 6 segundos). Conceito escolhido: **A chegada à casa pronta**.
Cada linha de texto abaixo embarca ao pé da letra. Números de faixa são pontos de partida, validados pelo teste de flick.

## Estado do projeto (para a próxima sessão)

- [x] Fase 1: ffmpeg, Node, PHP + SQLite + GD presentes. Higgsfield: o usuário vai conectar e abrir sessão nova.
- [x] Fase 2: empresa real; o usuário vai mandar fotos reais das obras; imagens de IA entram como ilustração e serão trocadas por fotos reais (sem aviso de IA no rodapé); sensação: solidez e alto padrão.
- [x] Fase 3: pesquisa feita (abaixo). Conceito escolhido: chegada à casa pronta.
- [x] Sistema pedido: pedidos de orçamento, painel de obras e clientes, gerador de orçamento em PDF, galeria de obras.
- [x] Sistema construído e testado (PHP + SQLite): painel, pedidos, clientes, obras com etapas e fotos, orçamento em PDF, galeria, página de acompanhamento.
- [x] Site construído com hero provisório: a casa se desenha em traço de planta com o scroll. Para ligar o vídeo: `data-video="assets/hero-scrub.mp4"`, `data-poster="assets/hero-poster.jpg"` e `data-bytes="<tamanho>"` no `.palco` do index.html. O traço vira o hero do celular.
- [x] Sistema v2: funcionários, diárias, vales, despesas por obra, fechamento quinzenal com recibos, custo por obra, menu lateral agrupado, modo claro e letras maiores no painel. Testado de ponta a ponta.
- [ ] Fase 6: gerar quadro inicial + vídeo no Higgsfield (prompts prontos na seção 10).
- [ ] Fase 7: processar vídeo (receitas ffmpeg), trocar o hero de traço pelo scrub.
- [ ] Fotos reais das obras na galeria.
- [x] Promessas confirmadas pelo usuário: pagamento por etapa, fotos do andamento, atende Brasília e entorno. Prazo em contrato NÃO é promessa da empresa: removido do site.
- [ ] Fase 10: HostGator.

## Pesquisa (linguagem real dos compradores)

Fontes: Reclame Aqui (casas pré moldadas e pré fabricadas), TJDFT (obras abandonadas no DF), blogs e fóruns sobre pré moldado.

- Dor 1, atraso: "deveria ser entregue em 60 dias", "mais de 6 meses para construir nossa casa". Desculpas comuns: chuva, falta de mão de obra.
- Dor 2, obra parada depois de pagar: "obra inacabada e contato bloqueado", "pagamos pela obra mas não tem sido executada". No DF, cliente pagou quase todo o contrato e a obra ficou sem terminar.
- Dor 3, sumiço: "falta de retorno da empresa", "descaso".
- Dúvidas do pré moldado: resistência, acabamento limitado, casa pequena, passagem de tubulação, financiamento.
- Desejo: rapidez, custo menor, "o alívio do stress".

UMA chamada para ação: **Pedir orçamento** (formulário que cai no painel + WhatsApp).

## Revisão de texto (pedido do cliente)

Todo o texto do site foi reescrito no `site/index.html`, que passa a ser a fonte do texto final. Regras da revisão: só promessas confirmadas (pagamento por etapa, fotos da obra no celular, WhatsApp dos donos, orçamento item por item, Brasília e entorno, serviços do cartão), nenhum número inventado, nada de "impecável", "alto padrão" ou "primeira linha". Entraram: selos no topo, a faixa "Antes de escolher quem vai construir, faça estas quatro perguntas", a pergunta "Vocês fazem só uma parte da obra?" e o botão flutuante de WhatsApp.

## 1. A premissa da marca

**No prumo.** Prumo é o fio com peso que o pedreiro solta para conferir se a parede está reta. A Farias Araújo vende obra no prumo: estrutura reta, prazo reto e conversa reta. Cada seção prova uma dessas três retas, e o site inteiro é atravessado por um fio de prumo dourado.

## 2. Paleta (tokens CSS)

Direção tirada do concreto, do grafite e do dourado do logo, no fim de tarde do cerrado. Valores finais revisados depois do vídeo aprovado.

```css
:root{
  --canvas:#121316;        /* grafite frio, nunca preto puro */
  --panel:#1b1c20;
  --panel-2:#23252a;
  --concrete:#8c8882;
  --accent:#c9a04a;        /* ouro do logo: CTA, foco, prumo */
  --accent-hover:#ddb65e;
  --accent-muted:rgba(201,160,74,.22);
  --text-secondary:#a9a49b;
  --text-primary:#ece7dd;
  --line:rgba(236,231,221,.12);
}
```

Desvio declarado: grafite com dourado e serifa é o visual padrão que a IA repete, mas aqui é o mundo material da própria marca (cartão e logo). Fica, com o elemento assinatura e os tons do concreto para ler como Farias Araújo.

## 3. Trio de fontes

- Display: **Cinzel** 500 e 600 (maiúsculas romanas, parentes da letra do logo).
- Texto: **Manrope** 400, 500, 700.
- Rótulos e cotas: **IBM Plex Mono** 400 e 500.

## 4. Mapa de faixas do hero (400vh, intervalo de scroll 300vh)

| Faixa | Intervalo | Momento da filmagem | Texto | Entrada |
|---|---|---|---|---|
| 1 | 0.00 a 0.30 | terreno no fim de tarde, casa pequena ao fundo | Kicker: "Construções pré moldadas · Distrito Federal". Título: "Sua casa no prumo." Linha: "Do projeto à entrega, sem sumiço no meio do caminho." | aproximação da profundidade (ecoa o avanço da câmera) |
| 2 | 0.34 a 0.64 | a casa cresce no quadro, estrutura visível | Título: "Peça por peça. Etapa por etapa." Linha: "Você paga conforme a obra avança e acompanha cada fase pelo celular." | alinhamento em grade (peças pré moldadas se encaixando) |
| 3 | 0.68 a 1.00 | chegada: casa pronta, luzes acesas | Título: "Do terreno à chave na mão." Botões: "Pedir orçamento" e "Chamar no WhatsApp" | subida palavra por palavra com assentamento em etapas |

## 5. Hero estático (celular, movimento reduzido, e enquanto o vídeo não existe)

Kicker: "Construções pré moldadas · Distrito Federal"
Título: "Sua casa no prumo."
Linha: "Casas pré moldadas e construção civil completa. Pagamento por etapa e a obra acompanhada pelo celular, com fotos."
Botões: "Pedir orçamento" e "Chamar no WhatsApp"

Até o vídeo existir, no computador a casa se desenha em traço de planta conforme o scroll (chão, base, pilares, vigas, laje, painéis, janelas, cotas, e as luzes acendem no fim). No celular ela se desenha sozinha ao abrir.

## 6. Abaixo da dobra

**01 · No prumo** (manifesto)
Título: "Obra reta. Conta reta. Conversa reta."
Texto: "Prumo é o fio com peso que o pedreiro solta para ver se a parede subiu reta. A gente usa a mesma régua para tudo: a estrutura que vai de pé, o dinheiro que você paga por etapa e o telefone que atende do primeiro dia até a entrega."
Três retas:
- "Estrutura reta" · "Peças de concreto feitas com medida certa. Menos improviso no canteiro, menos desperdício."
- "Conta reta" · "Você paga cada etapa quando ela fica pronta. Nada de adiantar a obra inteira."
- "Conversa reta" · "Kauan e David atendem pessoalmente. Sem central, sem sumiço."

**02 · Serviços**
Título: "Tudo que a obra pede, com a mesma equipe."
- Casas pré moldadas · "Casas modernas, rápidas de levantar e bem acabadas. Estrutura de concreto montada sobre a base certa para o seu terreno."
- Base e fundação · "Fundações sólidas para garantir segurança e estabilidade."
- Estruturação · "Estruturas resistentes e eficientes para obras de alto padrão."
- Construção civil em geral · "Do planejamento à execução, cuidamos de cada detalhe."
- Serviço elétrico · "Instalações seguras e eficientes, conforme as normas técnicas."
- Serviços hidráulicos · "Água fria, quente e esgoto, completos."
- Assentamento de revestimentos · "Cerâmica e porcelanato com precisão e acabamento impecável."
- Piso polido e camussado · "Resistência, durabilidade e acabamento que valoriza o ambiente."
- Pintura · "Acabamento perfeito com materiais de primeira linha."

**03 · Como funciona** (momento interativo: o prumo)
Título: "Segure e coloque no prumo."
Uma parede de placas pré moldadas aparece torta (desvio 3,8°). O visitante segura o botão "Segure para aprumar": o fio de prumo desce, a parede endireita e a leitura cai para 0,0°. Soltar cedo deixa a parede voltar devagar.
Ao completar: selo "No prumo" e a linha "No prumo. É essa a régua de cada etapa da sua obra." Os 5 passos acendem em sequência.
Passos ao lado:
1. "Visita e medida" · "Vamos até o terreno, ouvimos o que você quer e medimos tudo."
2. "Orçamento detalhado" · "Item por item, com valor de cada etapa. Sem número no chute."
3. "Tudo combinado" · "Etapas, valores e forma de pagamento acertados com você antes de começar."
4. "Obra por etapas" · "Você paga conforme cada etapa é entregue e recebe fotos do andamento."
5. "Entrega" · "Vistoria junto com você e a chave na mão."

**04 · Acompanhe sua obra**
Título: "Sua obra no celular, sem precisar ligar."
Texto: "Todo cliente recebe um código. Com ele você vê a etapa atual, as fotos mais recentes e o que vem a seguir."
Campo: rótulo "Código da obra", placeholder "Ex.: FA7K2Q", botão "Ver minha obra".

**05 · Obras**
Título: "Obras que já estão de pé."
Vazio: "As fotos das obras chegam aqui em breve. Enquanto isso, veja o dia a dia no Instagram." Botão: "Ver no Instagram"

**06 · Quem atende**
Título: "Você fala direto com quem constrói."
- Kauan · "Sócio" · 61 99440-8678
- David · "Sócio" · 61 99938-2439
Linha: "Construindo hoje, realizando o amanhã."

**07 · Perguntas**
- "Casa pré moldada é resistente?" · "É. A estrutura é de concreto, com pilares e vigas feitos na medida certa e montados sobre uma base dimensionada para o seu terreno. A diferença está em como a obra é feita, não em quanto ela aguenta."
- "Quanto tempo leva?" · "Depende do tamanho e do projeto. A previsão de cada etapa sai no orçamento, e pelo link de acompanhamento você vê a obra andando."
- "Como funciona o pagamento?" · "Por etapa. Você paga a próxima parte quando a anterior está feita e você viu com os próprios olhos, por foto ou visita."
- "O acabamento fica simples?" · "Não precisa ficar. A mesma equipe faz revestimento, piso polido, pintura, elétrica e hidráulica, então o acabamento sai no padrão que você escolher."
- "Dá para mudar a planta?" · "Dá. A planta é feita a partir do que você precisa e do tamanho do terreno."
- "Vocês atendem onde?" · "Brasília e entorno. Mande sua cidade no orçamento e a gente confirma."

**08 · Pedir orçamento** (a chamada para ação)
Título: "Conta pra gente o que você quer construir."
Texto: "Responda em um minuto. Kauan ou David te chamam no WhatsApp com os próximos passos."
Campos: "Seu nome" · "WhatsApp" (placeholder "61 9 0000-0000") · "Cidade ou região" (placeholder "Ex.: Ceilândia, Sobradinho, Valparaíso") · "O que você precisa" (lista de serviços) · "Tamanho aproximado em m²" (opcional) · "Quando quer começar" (Agora · Em até 3 meses · Ainda estou planejando) · "Conte um pouco mais" (opcional).
Botão: "Pedir meu orçamento"
Microtexto: "Seus dados vão só para a Farias Araújo."
Sucesso: "Recebido, {nome}. A gente te chama no WhatsApp em breve." Botão: "Falar agora no WhatsApp"
Destino: o formulário grava no painel (`/api/orcamento.php`). Se o servidor não responder, o botão abre o WhatsApp com a mensagem pronta.

**Rodapé:** logo, "Construções pré moldadas · Distrito Federal", e-mail farias araujo.construcoes@gmail.com, Instagram @obras_contrucoes.fariasaraujo, telefones, "Acesso da equipe" (link discreto para /painel).

## 7. Camada vetorial

- **Assinatura: o fio de prumo.** Uma linha dourada de 1px desce pela margem esquerda da página inteira, presa no topo; o peso (um losango de prumo em SVG) acompanha o scroll com leve balanço amortecido. Em cada seção, uma cota em mono marca a "altura" (01 · 02 ...).
- Planta baixa da casa em traço que se desenha no hero estático e na seção 03.
- Cotas com setas que se traçam nos títulos de seção.
- Grão de concreto fixo ao fundo + brilho dourado muito lento (90s).
- Tudo com movimento reduzido mostra o estado final.

## 8. Engenharia

Blob com anel, interpolação por dt, seeks travados, DOM por delta, faixas com teste de flick, legibilidade em quatro camadas, cinco portões vivos, completo sem vídeo, piso de qualidade: tudo de `references/pipeline-scrub.md`.

**Desvio declarado:** o site público é estático, mas o sistema usa PHP + SQLite no mesmo plano da HostGator (pasta `/painel`, `/api`, `acompanhar.php`). O banco fica FORA de `public_html` (`../fa-dados/`).

## 9. Gate de texto

Zero travessões, zero palavras de estoque, varredura de sinais de IA antes de mostrar. Trio deliberado: "Obra reta. Conta reta. Conversa reta." e "Peça por peça. Etapa por etapa." ficam.

## 10. Prompts de geração (prontos para a Fase 6)

Quadro inicial (imagem 16:9, 2k):

```
A modern single-story precast concrete house seen from the far end of a wide
red-earth lot in the Brazilian cerrado at golden hour, small in the center of
the frame, composed as the first moment of a slow forward push that will
arrive at its front facade. Low warm sun from the left, long soft shadows,
sky fading from amber to deep blue. Palette: exposed light-grey concrete
panels, dark graphite window frames, warm brushed-gold light, red cerrado
earth, dry golden grass. Atmosphere: fine dust drifting in the sunlight,
grass moving gently, a few birds far away. The lot and sky continue edge to
edge; the left and right thirds are calm open grass and soft sky where
captions will sit. Cinematic, photorealistic, 16:9. No text, no logos, no
lettering anywhere.
```

Vídeo (imagem para vídeo, 1080p, 6s, sem áudio):

```
One continuous shot, no cuts. The camera pushes slowly forward across the
lot toward the precast concrete house, from far away to a composed view of
its front facade, along a straight line through the center of the frame at
eye level. The scene stays alive: dry grass sways, dust drifts in the low
sun, the sky deepens from amber to blue, and the interior lights of the
house fade on one by one as the camera nears. The shot ends at rest: the
whole house sits centered with generous space above the roof and below the
front step, warm light glowing through the large windows, concrete walls
catching the last gold of the sun, the camera settled and still. No text or
lettering anywhere.
```
