<?php
require __DIR__.'/../../vendor/autoload.php';

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
 
    $clientId = 'informe_seu_client_id'; // insira seu Client_Id, conforme o ambiente (Des ou Prod)
    $clientSecret = 'informe_seu_client_secret'; // insira seu Client_Secret, conforme o ambiente (Des ou Prod)
 
    $options = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'sandbox' => true // altere conforme o ambiente (true = Homologação e false = producao)
    ];
 
    /*
    * Este token será recebido em sua variável que representa os parâmetros do POST
    * Ex.: $_POST['notification']
    */
    $token = $_POST["notification"];
 
    $params = [
        'token' => $token
    ];
 
    try {
        $api = new Gerencianet($options);
        $chargeNotification = $api->getNotification($params, []);
    // Para identificar o status atual da sua transação você deverá contar o número de situações contidas no array, pois a última posição guarda sempre o último status. Veja na um modelo de respostas na seção "Exemplos de respostas" abaixo.
  
    // Veja abaixo como acessar o ID e a String referente ao último status da transação.
    
    // Conta o tamanho do array data (que armazena o resultado)
    $i = count($chargeNotification["data"]);
    // Pega o último Object chargeStatus
    $ultimoStatus = $chargeNotification["data"][$i-1];
    // Acessando o array Status
    $status = $ultimoStatus["status"];
    // Obtendo o ID da transação    
    $charge_id = $ultimoStatus["identifiers"]["charge_id"];
    // Obtendo a String do status atual
    $statusAtual = $status["current"];
    
    // Com estas informações, você poderá consultar sua base de dados e atualizar o status da transação especifica, uma vez que você possui o "charge_id" e a String do STATUS
  
    if($statusAtual == "paid"){
        updateStatus(/**$user_id - Duvida */, "PAID");
    } else {
        updateStatus(/**$user_id - Duvida */, "REVISION");
    }
 
    //print_r($chargeNotification);
} catch (GerencianetException $e) {
    print_r($e->code);
    print_r($e->error);
    print_r($e->errorDescription);
} catch (Exception $e) {
    print_r($e->getMessage());
}
?>