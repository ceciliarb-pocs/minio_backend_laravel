<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Arquivo;
use GuzzleHttp\Client;

class ArquivoController extends Controller
{
    private static $API_MINIO_URL = '';
    private static $API_MINIO_AUTH = '';
    private static $FILE_FIELD_NAME = 'arquivo';
    private static $guzzleClient;

    public function __construct() {
        self::$API_MINIO_URL  = env('API_MINIO_URL');
        self::$API_MINIO_AUTH = env('MINIO_KEY').':'.env('MINIO_SECRET');
        self::$guzzleClient   = new Client();
    }

    public function gravaArquivo(Request $request)
    {
        $request->validate([
            self::$FILE_FIELD_NAME => 'required|file',
        ]);
        $file = $request->file(self::$FILE_FIELD_NAME);
        $arq  = null;

        try {
            DB::beginTransaction();
            //------------------ prepara objeto para salvar no banco
            $conteudo = \file_get_contents($file->path());
            $url      = self::$API_MINIO_URL;
            $result   = self::$guzzleClient->request('POST',  $url, [
                'headers'  => [ 'Authorization' => self::$API_MINIO_AUTH ],
                'multipart' => [ [ 'name'       => self::$FILE_FIELD_NAME,
                                   'contents'   => $conteudo,
                                   'filename'   => $file->getClientOriginalName() ] ]
                ]);

            $arq = Arquivo::salvaArquivoJson($result->getBody()->getContents());

            //------------------ salva no banco
            DB::commit();
            return json_encode($arq);

        } catch (\Throwable $th) {
            DB::rollback();
            throw new \Exception("[ERRO] Não foi possível processar o UPLOAD.".$th->getMessage(), 412);
        }
    }

    /**
     * $modo = "show" || "download" || "url"
     */
    public function recuperaArquivo($nome_arquivo, $modo="show") {
        try {
            $arq    = Arquivo::getByNome($nome_arquivo);
            if($arq===null) {
                throw new Exception("[ERRO] Arquivo não encontrado.", 412);
            }

            $url    = self::$API_MINIO_URL;
            $result = self::$guzzleClient->request('GET',  $url, [
                'headers'  => [ 'Authorization' => self::$API_MINIO_AUTH ],
                'query' => ['nome_arquivo' => $nome_arquivo, 'modo' => $modo]
            ]);
            $conteudo_arquivo = $result->getBody()->getContents();
            if($modo === "url") {
                return json_encode([ 'url' => $conteudo_arquivo ]);
            } else {
                $result->getBody()->detach();
                return json_encode([ 'Content' => base64_encode($conteudo_arquivo), 'ContentType' => $arq->mimetype, 'FileName' => $nome_arquivo]);
            }

        } catch (\Throwable $th) {
            throw new \Exception("[ERRO] Não foi possível processar o GET.".$th->getMessage(), 412);
        }
    }

    public function removeArquivo($nome_arquivo) {
        try {
            $arq    = Arquivo::getByNome($nome_arquivo);
            if($arq===null) {
                throw new \Exception("[ERRO] Arquivo não encontrado.", 412);
            }

            $url    = self::$API_MINIO_URL;
            $result = self::$guzzleClient->delete($url.'/'.$nome_arquivo, [ 'headers' => [ 'Authorization' => self::$API_MINIO_AUTH ] ]);
            $resposta = $result->getBody()->getContents();
            return json_encode([ 'msg' => $resposta ]);

        } catch (\Throwable $th) {
            throw new \Exception("[ERRO] Não foi possível processar o DELETE.".$th->getMessage(), 412);
        }
    }

}
