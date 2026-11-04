<?php
// Configuração central da Farias Araújo.
// O banco fica FORA da pasta pública: na HostGator, ao lado de public_html.
declare(strict_types=1);

define('FA_DATA', getenv('FA_DATA') ?: dirname(__DIR__, 2) . '/fa-dados');
define('FA_DB', FA_DATA . '/fa.sqlite');
define('FA_UPLOADS', dirname(__DIR__) . '/uploads');
define('FA_UPLOADS_URL', '/uploads');

define('FA_EMPRESA', 'Farias Araújo Construções Pré Moldadas');
define('FA_EMAIL', 'fariasaraujo.construcoes@gmail.com');
define('FA_INSTAGRAM', 'https://www.instagram.com/obras_contrucoes.fariasaraujo/');
define('FA_CONTATOS', [
    ['nome' => 'Kauan', 'whats' => '5561994408678', 'exibir' => '61 99440-8678'],
    ['nome' => 'David', 'whats' => '5561999382439', 'exibir' => '61 99938-2439'],
]);
define('FA_WHATS_PRINCIPAL', '5561994408678');

define('FA_SERVICOS', [
    'Casa pré moldada',
    'Base e fundação',
    'Estruturação',
    'Construção civil em geral',
    'Serviço elétrico',
    'Serviços hidráulicos',
    'Assentamento de revestimentos',
    'Piso polido e camussado',
    'Pintura',
    'Outro',
]);

define('FA_ETAPAS_PADRAO', ['Base e fundação', 'Pilares', 'Vigas e estrutura', 'Cobertura', 'Elétrica e hidráulica', 'Revestimento e piso', 'Pintura', 'Entrega']);

define('FA_CATEGORIAS', ['Combustível', 'Café da manhã', 'Almoço', 'Material', 'Transporte e frete', 'Aluguel de equipamento', 'Ferramentas', 'Cartões e divulgação', 'Taxas e documentos', 'Outros']);
// Ciclo de pagamento: 15 dias corridos contados a partir desta data (08/09 a 22/09, 23/09 a 07/10...).
define('FA_CICLO_INICIO', '2026-09-08');
define('FA_CICLO_DIAS', 15);
define('FA_FORMAS', ['Pix', 'Dinheiro', 'Cartão', 'Boleto', 'A prazo']);
define('FA_FUNCOES', ['Pedreiro', 'Servente', 'Mestre de obra', 'Eletricista', 'Encanador', 'Pintor', 'Montador', 'Carpinteiro', 'Armador', 'Ajudante', 'Outro']);

date_default_timezone_set('America/Sao_Paulo');
