<?php

function validation(){
    $servername = "localhost";
    $username = "username";
    $password = "password";
    $dbname = "myDB";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    // Check connection
    if ($conn->connect_error) {
     die("Connection failed: " . $conn->connect_error);
    }

    $sql = "SELECT account_id, valor_saque, saldo, statu FROM solicitacoes WHERE statu='PENDENTE'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {

        //Array para armazenar o Select de solicitações com status "PENDENTE"
        $spArray = array();

        // Armazena cada linha vinda do Select como Objeto no array $spArray
        while($row = $result->fetch_assoc()) {
            $spArray = [
                {
                    "id" => $row[]["account_id"],
                    "valor" => $row[]["valor_saque"],
                    "saldo" => $row[]["saldo"],
                    "status" => $row[]["statu"]
                }
            ];
        }

        $limiteSaque = 500;
        $aprovArray = array();
        $reprovArray = array();

        // Validação das solicitações que estão dentro do limite de saque e dentro do saldo da conta do usuário
        for($i = 0; $i < count($spArray); $i++){
            if($spArray[$i]['valor'] <= $limiteSaque && $spArray[$i]['valor'] <= $spArray[$i]['saldo']){
                // Armazena solicitações validadas
                $aprovArray = [
                    {
                        "id" => $spArray[$i]["account_id"],
                        "valor" => $spArray[$i]["valor_saque"],
                        "saldo" => $spArray[$i]["saldo"],
                        "status" => $spArray[$i]["EM_PROCESSAMENTO"]
                    }
                ];
            } else {
                // Armazena solicitações não validadas
                $reprovArray = [
                    {
                        "id" => $spArray[$i]["account_id"],
                        "valor" => $spArray[$i]["valor_saque"],
                        "saldo" => $spArray[$i]["saldo"],
                        "status" => $spArray[$i]["EM_REVISAO"]
                    }
                ];
            }
        }

        // Atualizar no BD o status das solicitações

        // Update a solicitação para status "EM_PRODUCAO"
        for($i = 0; $i < count($aprovArray); $i++){
            $sql = "UPDATE solicitacoe SET statu=$aprovArray[$i]["statu"] WHERE id=$aprovArray[$i]["id"]";

            if ($conn->query($sql) !== TRUE) {
                echo "Error updating record: " . $conn->error;
                
              } else if($conn->query($sql) === TRUE && $i == count($aprovArray)) {
                echo "Record updated successfully";
            }
        }

        // Update a solicitação para status "EM_REVISAO"
        for($i = 0; $i <= count($reprovArray); $i++;){
            $sql = "UPDATE solicitacoe SET statu=$reprovArray[$i]["statu"] WHERE id=$reprovArray[$i]["id"]";
        }

        if ($conn->query($sql) !== TRUE) {
            echo "Error updating record: " . $conn->error;

          } else if($conn->query($sql) === TRUE && $i == count($reprovArray)) {
            echo "Record updated successfully";
        }

        // Chamada função PIX
        callPixAPI();

    } else {
        echo "0 results";
    }
    $conn->close();
}
?>