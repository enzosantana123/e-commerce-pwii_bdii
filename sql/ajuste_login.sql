USE chronus;

-- Adiciona o campo para armazenar o hash da senha do cliente.
-- Execute este arquivo uma única vez no MySQL.
ALTER TABLE cliente
ADD COLUMN senha_clien VARCHAR(255) NULL AFTER cpf_clien;
