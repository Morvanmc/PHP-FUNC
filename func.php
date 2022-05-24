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

    $sql = "SELECT user_id, account_id, status, amount FROM withdrawals_transactions WHERE status='PENDING'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {

        //Array para armazenar o Select de solicitações com status "PENDENTE"
        $spArray = array();

        // Armazena cada linha vinda do Select como Objeto no array $spArray
        while($row = $result->fetch_assoc()) {
            $spArray = [
                {
                    "user_id" => $row[]["user_id"],
                    "account_id" => $row[]["account_id"],
                    "amount" => $row[]["amount"],
                    "status" => $row[]["status"]
                }
            ];
        }

        $limiteSaque = 500;
        $aprovArray = array();
        $reprovArray = array();

        // Validação das solicitações que estão dentro do limite de saque
        for($i = 0; $i < count($spArray); $i++){
            if($spArray[$i]['valor'] <= $limiteSaque){
                // Armazena solicitações validadas
                $aprovArray = [
                    {
                        "user_id" => $spArray[$i]["user_id"],
                        "account_id" => $spArray[$i]["account_id"],
                        "amount" => $spArray[$i]["amount"],
                        "status" => $spArray[$i]["status"]
                    }
                ];
            } else {
                // Armazena solicitações não validadas
                $reprovArray = [
                    {
                        "user_id" => $spArray[$i]["user_id"],
                        "account_id" => $spArray[$i]["account_id"],
                        "amount" => $spArray[$i]["amount"],
                        "status" => $spArray[$i]["status"]
                    }
                ];
            }
        }

        // Atualizar no BD o status das solicitações

        // Update a solicitação para status "PROCESSING"
        for($i = 0; $i < count($aprovArray); $i++){
            $sql = "UPDATE withdrawals_transactions SET status='PROCESSING' WHERE id=$aprovArray[$i]["user_id"]";

            if ($conn->query($sql) === TRUE) {
                echo "Record updated successfully";
                
              } else {
                echo "Error updating record: " . $conn->error;
            }

            // Chamada função PIX passando user_id
            callPixAPI($aprovArray[$i]["user_id"]);
        }

        // Update a solicitação para status "REVISION"
        for($i = 0; $i <= count($reprovArray); $i++;){
            $sql = "UPDATE withdrawals_transactions SET status='REVISION' WHERE id=$reprovArray[$i]["user_id"]";
        }

        if ($conn->query($sql) === TRUE) {
            echo "Record updated successfully";
            
          } else {
            echo "Error updating record: " . $conn->error;
        }

    } else {
        echo "0 results";
    }
    $conn->close();
}

function callPixAPI($user_id){
    
    /**CODE DB CONNECT */

    $sql = "SELECT name, cpf, bank_code, agency, account, amount 
            FROM withdrawals_transactions 
            WHERE status='PROCESSING' 
            AND user_id=$user_id";

    $result = $conn->query($sql);

    // Armazena a linha em um Objeto
    while($row = $result->fetch_assoc()) {
        $pixArray = {
            "user_id" => $user_id,
            "name" => $row["name"],
            "cpf" => $row["cpf"],
            "bank_code" => $row["bank_code"],
            "agency" => $row["agency"],
            "account" => $row["account"]
            "amount" => $row["amount"]
        };
    }

    $url = 'https://api-pix-h.gerencianet.com.br';
    $header = 'authorization: {{Authorization}}';
    $collection_name = 'v2/pix';
    
    $request_url = $url . '/' . $collection_name;

    $data = [
        {
            "valor": $pixArray->$amount,
            "pagador": {
              "chave": "Chave_BV",
              "infoPagador": "Segue o pagamento da conta"
            },
            "favorecido": {
              "contaBanco": {
                "nome": $pixArray->$name,
                "cpf": $pixArray->$cpf,
                "codigoBanco": $pixArray->$bank_code,
                "agencia": $pixArray->$agency,
                "conta": $pixArray->$account,
                "tipoConta": "cacc"
              }
            }
        }    
    ];

    $curl = curl_init($request_url);

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_POSTFIELDS,  json_encode($data));
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

    $response = curl_exec($curl);

    if($response == 200){
        $sql = "UPDATE withdrawals_transactions SET status='WAITING' WHERE user_id=$user_id";

        if ($conn->query($sql) === TRUE) {
            echo "Record updated successfully";
            
          } else {
            echo "Error updating record: " . $conn->error;
        }

    } else {
        $sql = "UPDATE withdrawals_transactions SET status='REVISION' WHERE user_id=$user_id";

        if ($conn->query($sql) === TRUE) {
            echo "Record updated successfully";
            
          } else {
            echo "Error updating record: " . $conn->error;
        }

    }
    curl_close($curl);
}  
?>