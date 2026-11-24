-- CHRONNUS - atualização para autenticação centralizada e catálogo dinâmico
-- Execute UMA ÚNICA VEZ depois de importar chronus.sql e executar ajuste_login.sql.
-- Esta atualização preserva as tabelas cliente, vendedor, pedido e item_pedido.

USE chronus;

-- 1. Cadastro central: um único login pode ser cliente ou vendedor.
CREATE TABLE IF NOT EXISTS usuario (
    id_usuario SMALLINT NOT NULL AUTO_INCREMENT,
    nome_usuario VARCHAR(80) NOT NULL,
    email_usuario VARCHAR(50) NOT NULL,
    telefone_usuario VARCHAR(15) NOT NULL,
    cpf_usuario CHAR(11) NOT NULL,
    senha_usuario VARCHAR(255) NOT NULL,
    tipo_usuario ENUM('cliente', 'vendedor', 'admin') NOT NULL DEFAULT 'cliente',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario),
    UNIQUE KEY uq_usuario_email (email_usuario),
    UNIQUE KEY uq_usuario_telefone (telefone_usuario),
    UNIQUE KEY uq_usuario_cpf (cpf_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Relaciona os perfis já existentes ao usuário sem apagar dados antigos.
ALTER TABLE cliente
    ADD COLUMN id_usuario SMALLINT NULL,
    ADD UNIQUE KEY uq_cliente_usuario (id_usuario),
    ADD CONSTRAINT fk_cliente_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE vendedor
    ADD COLUMN id_usuario SMALLINT NULL,
    ADD UNIQUE KEY uq_vendedor_usuario (id_usuario),
    ADD CONSTRAINT fk_vendedor_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE;

-- 3. Migra contas de clientes antigas que já possuem senha cadastrada.
-- Contas antigas sem senha continuam preservadas no perfil, mas não podem fazer login
-- até que seja criada uma senha por meio de um novo cadastro.
INSERT IGNORE INTO usuario
    (nome_usuario, email_usuario, telefone_usuario, cpf_usuario, senha_usuario, tipo_usuario)
SELECT
    c.nome_clien, c.email_clien, c.telefone_clien, c.cpf_clien, c.senha_clien, 'cliente'
FROM cliente c
WHERE c.senha_clien IS NOT NULL AND c.senha_clien <> '';

UPDATE cliente c
INNER JOIN usuario u ON BINARY u.email_usuario = BINARY c.email_clien
SET c.id_usuario = u.id_usuario
WHERE c.id_usuario IS NULL;

-- 4. Imagem e desconto passam a ser dados do produto.
INSERT INTO categoria (nome_categoria)
VALUES ('MAQUINAS'), ('PERIFERICOS')
ON DUPLICATE KEY UPDATE nome_categoria = VALUES(nome_categoria);

ALTER TABLE produto
    ADD COLUMN imagem_produ VARCHAR(255) NULL AFTER desc_produ,
    ADD COLUMN desconto_produ DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER preco_produ,
    MODIFY COLUMN id_categoria TINYINT NOT NULL,
    ADD KEY idx_produto_categoria (id_categoria),
    ADD CONSTRAINT fk_produto_categoria
        FOREIGN KEY (id_categoria) REFERENCES categoria (id_categoria)
        ON DELETE RESTRICT ON UPDATE CASCADE;

-- 5. Vendedor de catálogo para os oito produtos de demonstração.
-- Ele não possui login: os produtos publicados por usuários reais ficam vinculados
-- ao perfil vendedor criado no cadastro ou na conversão de conta.
INSERT INTO vendedor (nome_vende, email_vende, telefone_vende, cpf_vende)
SELECT 'Chronnus Store', 'catalogo@chronnus.local', '11900000000', '00000000000'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM vendedor WHERE email_vende = 'catalogo@chronnus.local'
);

SET @vendedor_catalogo = (
    SELECT id_vende FROM vendedor WHERE email_vende = 'catalogo@chronnus.local' LIMIT 1
);
SET @categoria_maquinas = (
    SELECT id_categoria FROM categoria WHERE nome_categoria = 'MAQUINAS' LIMIT 1
);
SET @categoria_perifericos = (
    SELECT id_categoria FROM categoria WHERE nome_categoria = 'PERIFERICOS' LIMIT 1
);

-- MAQUINAS: quatro produtos de exemplo.
INSERT INTO produto
    (id_vende, id_categoria, nome_produ, desc_produ, imagem_produ, preco_produ, desconto_produ)
SELECT @vendedor_catalogo, @categoria_maquinas,
       'PC Gamer Chronnus Core', 'Computador gamer para jogos atuais, criação de conteúdo e multitarefa.',
       'imgs/pc-gamer.png', 7799.90, 20.00
WHERE NOT EXISTS (SELECT 1 FROM produto WHERE nome_produ = 'PC Gamer Chronnus Core' AND id_vende = @vendedor_catalogo);

INSERT INTO produto
    (id_vende, id_categoria, nome_produ, desc_produ, imagem_produ, preco_produ, desconto_produ)
SELECT @vendedor_catalogo, @categoria_maquinas,
       'PC Chronnus Office', 'Máquina versátil para estudos, trabalho e produtividade diária.',
       'imgs/pc.png', 2699.90, 10.00
WHERE NOT EXISTS (SELECT 1 FROM produto WHERE nome_produ = 'PC Chronnus Office' AND id_vende = @vendedor_catalogo);

INSERT INTO produto
    (id_vende, id_categoria, nome_produ, desc_produ, imagem_produ, preco_produ, desconto_produ)
SELECT @vendedor_catalogo, @categoria_maquinas,
       'GeForce RTX 5060', 'Placa de vídeo para elevar o desempenho gráfico do seu setup.',
       'imgs/rtx5060.png', 2499.90, 0.00
WHERE NOT EXISTS (SELECT 1 FROM produto WHERE nome_produ = 'GeForce RTX 5060' AND id_vende = @vendedor_catalogo);

INSERT INTO produto
    (id_vende, id_categoria, nome_produ, desc_produ, imagem_produ, preco_produ, desconto_produ)
SELECT @vendedor_catalogo, @categoria_maquinas,
       'Placa Mãe Chronnus H510', 'Base estável e eficiente para montar ou atualizar seu computador.',
       'imgs/placa-mae.png', 549.90, 15.00
WHERE NOT EXISTS (SELECT 1 FROM produto WHERE nome_produ = 'Placa Mãe Chronnus H510' AND id_vende = @vendedor_catalogo);

-- PERIFERICOS: quatro produtos de exemplo.
INSERT INTO produto
    (id_vende, id_categoria, nome_produ, desc_produ, imagem_produ, preco_produ, desconto_produ)
SELECT @vendedor_catalogo, @categoria_perifericos,
       'Teclado Mecânico Vortex', 'Teclas responsivas e iluminação verde para uma digitação precisa.',
       'imgs/periferico-teclado.png', 349.90, 10.00
WHERE NOT EXISTS (SELECT 1 FROM produto WHERE nome_produ = 'Teclado Mecânico Vortex' AND id_vende = @vendedor_catalogo);

INSERT INTO produto
    (id_vende, id_categoria, nome_produ, desc_produ, imagem_produ, preco_produ, desconto_produ)
SELECT @vendedor_catalogo, @categoria_perifericos,
       'Mouse Gamer Pulse', 'Formato ergonômico, sensor preciso e iluminação discreta.',
       'imgs/periferico-mouse.png', 189.90, 0.00
WHERE NOT EXISTS (SELECT 1 FROM produto WHERE nome_produ = 'Mouse Gamer Pulse' AND id_vende = @vendedor_catalogo);

INSERT INTO produto
    (id_vende, id_categoria, nome_produ, desc_produ, imagem_produ, preco_produ, desconto_produ)
SELECT @vendedor_catalogo, @categoria_perifericos,
       'Headset Chronnus Sonic', 'Áudio imersivo, almofadas confortáveis e microfone integrado.',
       'imgs/periferico-headset.png', 429.90, 15.00
WHERE NOT EXISTS (SELECT 1 FROM produto WHERE nome_produ = 'Headset Chronnus Sonic' AND id_vende = @vendedor_catalogo);

INSERT INTO produto
    (id_vende, id_categoria, nome_produ, desc_produ, imagem_produ, preco_produ, desconto_produ)
SELECT @vendedor_catalogo, @categoria_perifericos,
       'Monitor Chronnus 27', 'Tela ampla para jogos e produtividade com visual de bordas finas.',
       'imgs/periferico-monitor.png', 1199.90, 5.00
WHERE NOT EXISTS (SELECT 1 FROM produto WHERE nome_produ = 'Monitor Chronnus 27' AND id_vende = @vendedor_catalogo);

-- Conferência: deve retornar MAQUINAS = 4 e PERIFERICOS = 4 no banco recém-importado.
SELECT c.nome_categoria, COUNT(p.id_produ) AS total_produtos
FROM categoria c
LEFT JOIN produto p ON p.id_categoria = c.id_categoria
GROUP BY c.id_categoria, c.nome_categoria
ORDER BY c.nome_categoria;
