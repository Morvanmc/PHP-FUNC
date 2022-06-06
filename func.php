/**
     * Validate and update withdrawals transaction status
     */
    public function validateWithdrawals()
    {
        $pendingTransactions = DB::Table('withdrawals_transactions')->where('status', 'PENDENTE')->get();

        if (count($pendingTransactions) > 0) {

            $limit = 50;

            foreach ($pendingTransactions as $pendingTransaction) {
                $cpf = DB::Table('user')->where('cpf', $pendingTransaction->cpf)->first();

                if ($cpf) {
                    $account = DB::Table('accounts')->where('user_id', $pendingTransaction->user_id)->latest();

                    if ($pendingTransaction->amount <= $limit && $pendingTransaction->amount <= $account->balance) {
                        WithdrawalsController::updateWithdrawals($pendingTransaction->user_id, 'PROCESS');

                        $response = WithdrawalsController::requestPix($pendingTransaction);

                        if ($response == 200) {
                            WithdrawalsController::updateWithdrawals($pendingTransaction->user_id, 'WAITING');
                            //fazer insert em uma table cashout gerencianet (e2E, id_withdrawals_transaction, status)
                            //webhook(e2E)-> compara na cashout-> muda status na withdrawals e na cashout
                        } else {
                            WithdrawalsController::updateWithdrawals($pendingTransaction->user_id, 'REVISION');
                        }
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
        DB::table('withdrawals_transactions')->where('user_id', $user_id)->update(['status' => $status]);
    }

    /**
     * getISPB
     */
    public function getISPB()
    {
        $url = 'https://brasilapi.com.br/api/banks/v1/063';
        $headers = [
            'Content-type: application/json'
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $headers
        ]);

        $response = curl_exec($curl);
        $response = json_decode($response, true);

        curl_close($curl);

        return $response;
    }
    /**
     * Call PIX API Gerencianet
     */

    public function requestPix($transaction)
    {
        $tokens = DB::Table('tokens')->where('type', 'gerencia_net')->first();
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

        $ispb = WithdrawalsController::getISPB($transaction->bank_code);

        $data = [
            "valor" => "99.99",
            "pagador" => [
                "chave" => env('CLIENT_PIX_KEY'),
                "infoPagador" => "Segue o pagamento da conta"
            ],
            "favorecido" => [
                "nome" => "JOSE CARVALHO",
                "cpf" => "10519952057",
                "codigoBanco" => $ispb,
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

    /**
     * Webhook Gerencianet
     */
    /*public function webhookResponse()
    {
        try {
            $api = new Gerencianet();
            $callbackNotification = $api->getNotification();

            if (count($callbackNotification) > 0) {
                $e2E = $callbackNotification->$pix->endToEndId;

                if ($callbackNotification->$pix->status == "REALIZADO") {
                    $payment = DB::Table('user_payments')->where('endToEndId', $e2E)->get();

                    DB::table('withdrawals_transactions')->where('user_id', $payment->user_id)->update(['status' => 'PAID']);
                } else {
                    $payment = DB::Table('user_payments')->where('endToEndId', $e2E)->get();

                    DB::table('withdrawals_transactions')->where('user_id', $payment->user_id)->update(['status' => 'REVISION']);
                }
            } else {
                return "Não há atualizações!";
            }
        } catch (GerencianetException $e) {
            print_r($e->code);
            print_r($e->error);
            print_r($e->errorDescription);
        } catch (Exception $e) {
            print_r($e->getMessage());
        }
    }*/
                        
                        //Route::post('pix', [PixController::class, 'webhookPixCashout']);
                         Route::get('validates', [WithdrawalsController::class, 'validateWithdrawals']);
        Route::post('withdrawalsPix', [WithdrawalsController::class, 'requestPix']);
        Route::get('ispb', [WithdrawalsController::class, 'getISPB']);
