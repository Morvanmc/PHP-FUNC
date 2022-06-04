/**
     * Validate and update withdrawals transaction status
     */
    public function validateWithdrawals()
    {
        $pendingTransactions = DB::Table('withdrawals_transactions')->where('status', 'PENDENTE')->get();

        if (count($pendingTransactions) > 0) {
            $limit = 50;

            foreach ($pendingTransactions as $pendingTransaction) {
                if ($pendingTransaction->amount <= $limit) {
                    WithdrawalsController::updateWithdrawals($pendingTransaction->user_id, 'PROCESS');
                    
                    $response = WithdrawalsController::requestPix($pendingTransaction);

                    if ($response == 200) {
                        WithdrawalsController::updateWithdrawals($pendingTransaction->user_id, 'WAITING');
                    } else {
                        WithdrawalsController::updateWithdrawals($pendingTransaction->user_id, 'REVISION');
                    }
                } else {
                    WithdrawalsController::updateWithdrawals($pendingTransaction->user_id, 'REVISION');
                }
            }
        } else {
            return 'Tudo certo por aqui! Nenhuma solicitação pendente.';
        }
    }

    /**
     * updateWithdrawals
     */

     public function updateWithdrawals($user_id, $status)
     {
        DB::table('withdrawals_transactions')->where('user_id',$user_id)->update(['status' => $status]);
     }

    /**
     * Call PIX API Gerencianet
     */

    public function requestPix()
    {
        $tokens = DB::Table('tokens')->where('type', 'hub2b')->first();
        if ($tokens) {
            $token = $tokens->token;
        }

        $url = env('ENDPOINT');
        $headers = [
            'Cache-Control: no-cache',
            'Content-type: application/json',
            'Authorization: Bearer ' . $token
        ];

        $collection_name = 'v2/pix';
        $certificate =  __DIR__ . env('CERTIFICATE');
        $endPoint = $url . '/' . $collection_name;
        print_r($endPoint);
        exit;
        $data = [
            "valor" => "99.99",
            "pagador" => [
                "chave" => "19974764017",
                "infoPagador" => "Segue o pagamento da conta"
            ],
            "favorecido" => [
                "nome" => "JOSE CARVALHO",
                "cpf" => "10519952057",
                "codigoBanco" => "09089356",
                "agencia" => "1",
                "conta" => "123453",
                "tipoConta" => "cacc"
            ]
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $endPoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_SSLCERT => $certificate,
            CURLOPT_SSLCERTPASSWD => '',
            CURLOPT_HTTPHEADER => $headers
        ]);

        $response = curl_exec($curl);
        $response = json_decode($response, true);
        
        curl_close($curl);

        return $response;     
    }
