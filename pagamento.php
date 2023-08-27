<?php


trataPagamento($payment_info);




function trataPagamento($payment_info, $calback = false)
{
    try {
        if (!verificaEmailValido($payment_info['email'])) lancaErro("Email inválido");
        if (!verificaInformacoesProdutos($payment_info['products'])) lancaErro("Erro ao obter informacoes dos produtos, alguns dados estao vazios");
        if (!salvaPagamento($payment_info, "conexao_banco")) lancaErro("Ocorreu um erro ao salvar");
        if ($calback) $calback();
    } catch (Error $e) {
        echo "Houve um erro: " . $e->getMessage();
    }
}


function verificaInformacoesProdutos($products)
{
    $informacoes = array_filter($products, function ($value) {
        return $value !== "" && $value > 0;
    });

    return count($informacoes) === count($products);
}

function verificaEmailValido($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function lancaErro($message)
{
    throw new Exception($message);
}

function salvaPagamento($payment_info, $con)
{
    try {
        $nome = $payment_info['nome'];
        $email = $payment_info['email'];
        $valor_total = $payment_info['valor_total'];

        $sql = "INSERT INTO payments (nome, email, valor_total) VALUES (:nome, :email, :valor_total)";

        $stmt = $con->prepare($sql);

        $stmt->bindValue(':nome', $nome);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':valor_total', $valor_total);

        $stmt->execute();

        $payment_id = $con->lastInsertId();

        salvaProdutos($payment_info['products'], $payment_id, $con);
        salvaMetodosPagamento($payment_info['paymentMethods'], $payment_id, $con);
    } catch (Error $e) {
        echo "Erro ao salvar pagamento: ->" . $e->getMessage();
    }
}

function salvaProdutos($produtos, $payment_id, $con)
{
    $sql = "INSERT INTO products (payment_id, name, quantity, unitValue, category) VALUES (:payment_id, :name, :quantity, :unitValue, :category)";
    $stmt = $con->prepare($sql);

    foreach ($produtos as $produto) {
        $stmt->bindValue(':payment_id', $payment_id);
        $stmt->bindValue(':name', $produto['name']);
        $stmt->bindValue(':quaantity', $produto['quantity']);
        $stmt->bindValue(':unitValue', $produto['unitValue']);
        $stmt->bindValue(':category', $produto['category']);
        $stmt->execute();
    }
}


function salvaMetodosPagamento($metodos, $payment_id, $con)
{
    $sql = "INSERT INTO payment_methods (payment_id, id, value, type) VALUES (:payment_id, :id, :value, :type)";
    $stmt = $con->prepare($sql);

    foreach ($metodos as $metodo) {
        $stmt->bindValue(':payment_id', $payment_id);
        $stmt->bindValue(':id', $metodo['id']);
        $stmt->bindValue(':quaantity', $metodo['value']);
        $stmt->bindValue(':unitValue', $metodo['type']);
        $stmt->execute();
        if ($metodo['type'] === "credit-card-paypal") salvaInformacaoPagamentoPayPal($metodo['id'], $payment_id, $con);
    }
}


function salvaInformacaoPagamentoPayPal($metodo_id, $payment_id, $con)
{
    $sql = "INSERT INTO payment_aux (id, payment_id, message) VALUES (:id, :payment_id, 'paypal pagou')";
    $stmt = $con->prepare($sql);
    $stmt->bindValue(':id', $metodo_id);
    $stmt->bindValue(':payment_id', $payment_id);
    $stmt->execute();
}
