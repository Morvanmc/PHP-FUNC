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

    $sql = "SELECT user_id, name, cpf, bank_code, agency, account, amount, status 
            FROM withdrawals_transactions 
            WHERE status='PENDING'";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {

        //Array para armazenar o Select de solicitações com status "PENDING"
        $spArray = array();

        // Armazena cada linha vinda do Select como Objeto no array $spArray
        while($row = $result->fetch_assoc()) {
            $spArray = [
                {
                    "user_id" => $row[]["user_id"],
                    "name" => $row[]["name"],
                    "cpf" => $row[]["cpf"],
                    "bank_code" => $row[]["bank_code"],
                    "agency" => $row[]["agency"],
                    "account" => $row[]["account"],
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
                        "name" => $spArray[$i]["name"],
                        "cpf" => $spArray[$i]["cpf"],
                        "bank_code" => $spArray[$i]["bank_code"],
                        "agency" => $spArray[$i]["agency"],
                        "account" => $spArray[$i]["account"],
                        "amount" => $spArray[$i]["amount"],
                        "status" => $spArray[$i]["status"]
                    }
                ];

                // Atualiza o status da solicitação para PROCESSING
                updateStatus($aprovArray[$i]["user_id"], "PROCESSING");

                // Chamada função PIX e armazena a resposta da gerencianet
                $response = callPixAPI($aprovArray[$i]);

                if($response == 200) {
                    // Atualiza o status da solicitação para WAITING
                    updateStatus($aprovArray[$i]["user_id"], "WAITING");
                } else {
                    // Atualiza o status da solicitação para REVISION
                    updateStatus($aprovArray[$i]["user_id"], "REVISION");
                }

            } else {
                // Armazena solicitações não validadas
                $reprovArray = [
                    {
                        "user_id" => $spArray[$i]["user_id"],
                        "name" => $spArray[$i]["name"],
                        "cpf" => $spArray[$i]["cpf"],
                        "bank_code" => $spArray[$i]["bank_code"],
                        "agency" => $spArray[$i]["agency"],
                        "account" => $spArray[$i]["account"],
                        "amount" => $spArray[$i]["amount"],
                        "status" => $spArray[$i]["status"]
                    }
                ];

                // Atualiza o status da solicitação para REVISION
                updateStatus($reprovArray[$i]["user_id"], "REVISION");
            }
        }
    } else {
        echo "0 results";
    }
    $conn->close();
}

function updateStatus($user_id, $status){
    $sql = "UPDATE withdrawals_transactions SET status=$status WHERE id=$user_id";

     if ($conn->query($sql) === TRUE) {
        echo "Record updated successfully";
                
    } else {
        echo "Error updating record: " . $conn->error;
    }
}

function callPixAPI($obj){

    $url = 'https://api-pix-h.gerencianet.com.br';
    $header = 'authorization: {{Authorization}}';
    $collection_name = 'v2/pix';
    
    $request_url = $url . '/' . $collection_name;

    $data = [
        {
            "valor": $obj->$amount,
            "pagador": {
              "chave": "Chave_BV",
              "infoPagador": "Segue o pagamento da conta"
            },
            "favorecido": {
              "contaBanco": {
                "nome": $obj->$name,
                "cpf": $obj->$cpf,
                "codigoBanco": $obj->$bank_code,
                "agencia": $obj->$agency,
                "conta": $obj->$account,
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

    return $response;

    curl_close($curl);
}

function webhookResponse() {

    $e2E = $pix->endToEndId //Callback da gerencianet - dúvida

    $sql = "SELECT user_id
            FROM user_payments 
            WHERE endToEndId=$e2E";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $pix->status === "REALIZADO" ? 
        updateStatus($result->user_id, "CONFIRM") : 
        updateStatus($result->user_id, "REVISION");
    }else {
        echo "0 results";
    }

    $conn->close();
}
?>