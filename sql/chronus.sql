-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: chronus
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE DATABASE IF NOT EXISTS `chronus` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `chronus`;

--
-- Table structure for table `categoria`
--

DROP TABLE IF EXISTS `categoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE DATABASE IF NOT EXISTS `chronus` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `chronus`;

DROP TABLE IF EXISTS `vendedor`;
CREATE TABLE `vendedor` (
  `id_vende` smallint NOT NULL AUTO_INCREMENT,
  `nome_vende` varchar(80) NOT NULL,
  `email_vende` varchar(50) NOT NULL,
  `telefone_vende` varchar(15) NOT NULL,
  `cpf_vende` char(11) NOT NULL,
  PRIMARY KEY (`id_vende`),
  UNIQUE KEY `id_vende` (`id_vende`),
  UNIQUE KEY `email_vende` (`email_vende`),
  UNIQUE KEY `telefone_vende` (`telefone_vende`),
  UNIQUE KEY `cpf_vende` (`cpf_vende`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cliente`;
CREATE TABLE `cliente` (
  `id_clien` smallint NOT NULL AUTO_INCREMENT,
  `nome_clien` varchar(80) NOT NULL,
  `email_clien` varchar(50) NOT NULL,
  `telefone_clien` varchar(15) NOT NULL,
  `cpf_clien` char(11) NOT NULL,
  `senha_clien` varchar(255) NULL,
  PRIMARY KEY (`id_clien`),
  UNIQUE KEY `id_clien` (`id_clien`),
  UNIQUE KEY `email_clien` (`email_clien`),
  UNIQUE KEY `telefone_clien` (`telefone_clien`),
  UNIQUE KEY `cpf_clien` (`cpf_clien`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `categoria`;
CREATE TABLE `categoria` (
  `id_categoria` tinyint NOT NULL AUTO_INCREMENT,
  `nome_categoria` varchar(20) NOT NULL,
  PRIMARY KEY (`id_categoria`),
  UNIQUE KEY `id_categoria` (`id_categoria`),
  UNIQUE KEY `nome_categoria` (`nome_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `produto`;
CREATE TABLE `produto` (
  `id_produ` smallint NOT NULL AUTO_INCREMENT,
  `id_vende` smallint NOT NULL,
  `id_categoria` tinyint NOT NULL,
  `nome_produ` varchar(30) NOT NULL,
  `desc_produ` varchar(500) DEFAULT NULL,
  `imagem_produ` varchar(255) NULL,
  `preco_produ` decimal(10,2) NOT NULL,
  `desconto_produ` decimal(5,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_produ`),
  UNIQUE KEY `id_produ` (`id_produ`),
  KEY `id_vende` (`id_vende`),
  KEY `idx_produto_categoria` (`id_categoria`),
  CONSTRAINT `produto_ibfk_1` FOREIGN KEY (`id_vende`) REFERENCES `vendedor` (`id_vende`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_produto_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categoria` (`id_categoria`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `endereco`;
CREATE TABLE `endereco` (
  `id_endereco` smallint NOT NULL AUTO_INCREMENT,
  `id_clien` smallint NOT NULL,
  `endereco` varchar(70) NOT NULL,
  PRIMARY KEY (`id_endereco`),
  UNIQUE KEY `id_endereco` (`id_endereco`),
  KEY `id_clien` (`id_clien`),
  CONSTRAINT `endereco_ibfk_1` FOREIGN KEY (`id_clien`) REFERENCES `cliente` (`id_clien`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `pedido`;
CREATE TABLE `pedido` (
  `id_pedido` smallint NOT NULL AUTO_INCREMENT,
  `id_clien` smallint NOT NULL,
  `data_pedido` datetime NOT NULL,
  `valor_total` decimal(10,2) NOT NULL,
  `status` enum('pendente','pago','cancelado') NOT NULL DEFAULT 'pendente',
  PRIMARY KEY (`id_pedido`),
  UNIQUE KEY `id_pedido` (`id_pedido`),
  KEY `id_clien` (`id_clien`),
  CONSTRAINT `pedido_ibfk_1` FOREIGN KEY (`id_clien`) REFERENCES `cliente` (`id_clien`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `item_pedido`;
CREATE TABLE `item_pedido` (
  `id_pedido` smallint NOT NULL,
  `id_produ` smallint NOT NULL,
  `quantidade` smallint NOT NULL DEFAULT '1',
  `preco_unitario` decimal(10,2) NOT NULL,
  `desconto` decimal(5,2) DEFAULT '0.00',
  `preco_final` decimal(10,2) GENERATED ALWAYS AS (((`quantidade` * `preco_unitario`) * (1 - (`desconto` / 100)))) STORED,
  PRIMARY KEY (`id_pedido`,`id_produ`),
  KEY `id_produ` (`id_produ`),
  CONSTRAINT `item_pedido_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedido` (`id_pedido`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `item_pedido_ibfk_2` FOREIGN KEY (`id_produ`) REFERENCES `produto` (`id_produ`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `usuario`;
CREATE TABLE `usuario` (
    `id_usuario` SMALLINT NOT NULL AUTO_INCREMENT,
    `nome_usuario` VARCHAR(80) NOT NULL,
    `email_usuario` VARCHAR(50) NOT NULL,
    `telefone_usuario` VARCHAR(15) NOT NULL,
    `cpf_usuario` CHAR(11) NOT NULL,
    `senha_usuario` VARCHAR(255) NOT NULL,
    `tipo_usuario` ENUM('cliente', 'vendedor', 'admin') NOT NULL DEFAULT 'cliente',
    `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_usuario`),
    UNIQUE KEY `uq_usuario_email` (`email_usuario`),
    UNIQUE KEY `uq_usuario_telefone` (`telefone_usuario`),
    UNIQUE KEY `uq_usuario_cpf` (`cpf_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `cliente`
    ADD COLUMN `id_usuario` SMALLINT NULL,
    ADD UNIQUE KEY `uq_cliente_usuario` (`id_usuario`),
    ADD CONSTRAINT `fk_cliente_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `vendedor`
    ADD COLUMN `id_usuario` SMALLINT NULL,
    ADD UNIQUE KEY `uq_vendedor_usuario` (`id_usuario`),
    ADD CONSTRAINT `fk_vendedor_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

INSERT INTO `categoria` (`nome_categoria`)
VALUES ('MAQUINAS'), ('PERIFERICOS'), ('VARIADOS')
ON DUPLICATE KEY UPDATE `nome_categoria` = VALUES(`nome_categoria`);

INSERT INTO `vendedor` (`nome_vende`, `email_vende`, `telefone_vende`, `cpf_vende`)
SELECT 'Chronnus Store', 'catalogo@chronnus.local', '11900000000', '00000000000'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `vendedor` WHERE `email_vende` = 'catalogo@chronnus.local'
);

SET @vendedor_catalogo = (SELECT `id_vende` FROM `vendedor` WHERE `email_vende` = 'catalogo@chronnus.local' LIMIT 1);
SET @categoria_maquinas = (SELECT `id_categoria` FROM `categoria` WHERE `nome_categoria` = 'MAQUINAS' LIMIT 1);
SET @categoria_perifericos = (SELECT `id_categoria` FROM `categoria` WHERE `nome_categoria` = 'PERIFERICOS' LIMIT 1);
SET @categoria_variados = (SELECT `id_categoria` FROM `categoria` WHERE `nome_categoria` = 'VARIADOS' LIMIT 1);

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

SELECT c.nome_categoria, COUNT(p.id_produ) AS total_produtos
FROM categoria c
LEFT JOIN produto p ON p.id_categoria = c.id_categoria
GROUP BY c.id_categoria, c.nome_categoria
ORDER BY c.nome_categoria;

SHOW COLUMNS FROM produto;	

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;