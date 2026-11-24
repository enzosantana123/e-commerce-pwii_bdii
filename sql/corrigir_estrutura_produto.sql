-- CHRONNUS - CORRECAO DA ESTRUTURA DA TABELA produto
-- Banco esperado: chronus
-- Execute este arquivo no HeidiSQL selecionando o banco chronus.
-- Seguro para executar mais de uma vez: verifica colunas, indices e FK antes de criar.

USE chronus;

DELIMITER $$

DROP PROCEDURE IF EXISTS corrigir_estrutura_produto_chronnus$$
CREATE PROCEDURE corrigir_estrutura_produto_chronnus()
BEGIN
    DECLARE v_existe INT DEFAULT 0;
    DECLARE v_orfaos INT DEFAULT 0;

    -- A tabela categoria precisa existir antes da FK.
    CREATE TABLE IF NOT EXISTS categoria (
        id_categoria TINYINT NOT NULL AUTO_INCREMENT,
        nome_categoria VARCHAR(20) NOT NULL,
        PRIMARY KEY (id_categoria),
        UNIQUE KEY uq_categoria_nome (nome_categoria)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Garante as duas categorias usadas pela loja.
    INSERT INTO categoria (nome_categoria)
    VALUES ('MAQUINAS'), ('PERIFERICOS')
    ON DUPLICATE KEY UPDATE nome_categoria = VALUES(nome_categoria);

    -- Verifica e cria a coluna de imagem esperada por index.php,
    -- compra.php e vendedor/index.php.
    SELECT COUNT(*) INTO v_existe
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produto'
      AND COLUMN_NAME = 'imagem_produ';

    IF v_existe = 0 THEN
        ALTER TABLE produto
            ADD COLUMN imagem_produ VARCHAR(255) NULL AFTER desc_produ;
    END IF;

    -- Verifica e cria a coluna de desconto esperada pelo catálogo e checkout.
    SELECT COUNT(*) INTO v_existe
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produto'
      AND COLUMN_NAME = 'desconto_produ';

    IF v_existe = 0 THEN
        ALTER TABLE produto
            ADD COLUMN desconto_produ DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER preco_produ;
    END IF;

    -- A tabela categoria usa TINYINT. A coluna relacionada precisa ter
    -- exatamente o mesmo tipo e sinal para aceitar a chave estrangeira.
    SELECT COUNT(*) INTO v_existe
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produto'
      AND COLUMN_NAME = 'id_categoria';

    IF v_existe = 0 THEN
        ALTER TABLE produto
            ADD COLUMN id_categoria TINYINT NULL;
    ELSE
        ALTER TABLE produto
            MODIFY COLUMN id_categoria TINYINT NOT NULL;
    END IF;

    -- Cria índice se ainda não existir.
    SELECT COUNT(*) INTO v_existe
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produto'
      AND INDEX_NAME = 'idx_produto_categoria';

    IF v_existe = 0 THEN
        ALTER TABLE produto ADD INDEX idx_produto_categoria (id_categoria);
    END IF;

    -- Só cria a FK se não houver produtos apontando para categorias inexistentes.
    SELECT COUNT(*) INTO v_orfaos
    FROM produto p
    LEFT JOIN categoria c ON c.id_categoria = p.id_categoria
    WHERE c.id_categoria IS NULL;

    SELECT COUNT(*) INTO v_existe
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produto'
      AND CONSTRAINT_NAME = 'fk_produto_categoria'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY';

    IF v_existe = 0 AND v_orfaos = 0 THEN
        ALTER TABLE produto
            ADD CONSTRAINT fk_produto_categoria
            FOREIGN KEY (id_categoria) REFERENCES categoria (id_categoria)
            ON DELETE RESTRICT ON UPDATE CASCADE;
    END IF;

    IF v_orfaos > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Existem produtos com id_categoria sem correspondencia. Corrija esses registros antes de criar a chave estrangeira.';
    END IF;
END$$

CALL corrigir_estrutura_produto_chronnus()$$
DROP PROCEDURE corrigir_estrutura_produto_chronnus$$

DELIMITER ;

-- Conferencia: estas colunas devem aparecer no resultado.
SHOW COLUMNS FROM produto;
