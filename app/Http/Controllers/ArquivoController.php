<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Arquivo;
use GuzzleHttp\Client;

class ArquivoController extends Controller
{
    public function gravaArquivo(Request $request)
    {
        $request->validate([
            'arquivo' => 'required|file',
        ]);

        try {
            DB::beginTransaction();
            //------------------ prepara objeto para salvar no banco
            $arq_req       = $request->file('arquivo');
            $arq           = new Arquivo();
            $arq->nome     = $arq_req->getClientOriginalName();
            $arq->hash     = \hash('sha256', $arq->nome.\time());
            $arq->extensao = $arq_req->getClientOriginalExtension();
            $arq->mimetype = \mime_content_type($arq_req->getPathname());
            $arq->save();

            //------------------ envia objeto para API salvar no storage
            $client           = new Client();
            $conteudo_arquivo = base64_encode(file_get_contents($arq_req->path()));
            $sobrescreve      = $request->sobrescreve || false;
            $url              = env('API_MINIO_URL').'/api/uploadStorage';
            $result           = $client->request('POST',  $url, [
                'headers' => [
                    'content-type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'nome_arquivo'   => $arq->hash,
                    'corpo_arquivo'  => $conteudo_arquivo,
                    'sobrescreve'    => $sobrescreve
                ],
                'http_errors' => false
            ]);

            //------------------ salva no banco
            DB::commit();
            return response($result->getStatusCode() == 200);

        } catch (\Throwable $th) {

            DB::rollback();
            return json_encode('false');
        }
    }

    private function getArquivo($hash_arquivo, $download=false) {
        try {
            $hash             = $hash_arquivo;
            $arq              =  Arquivo::where('hash', $hash)->first();
            if(!$arq)  return "[ERRO] Arquivo não encontrado.";
            $hash             =  $arq->hash;

            $client           = new Client();
            $url              = env('API_MINIO_URL').'/api/getStorage';
            $result           = $client->request('GET',  $url, [
                'query'       => ['nome_arquivo' => $hash],
                'http_errors' => false
            ]);
            $conteudo_arquivo = $result->getBody();
            if(base64_encode(base64_decode($conteudo_arquivo)) === $conteudo_arquivo) {
                $conteudo_arquivo = base64_decode($conteudo_arquivo);
            } else {
                $conteudo_arquivo = $conteudo_arquivo;
            }

            if($download) {
                return response($conteudo_arquivo, 200, ['Content-Type' => $arq->mimetype, 'Content-Disposition' => "attachment; filename=$arq->nome"]);
            } else {
                return response($conteudo_arquivo, 200, [ 'Content-Type' => $arq->mimetype, 'Content-Disposition' => "filename=$arq->nome"]);
            }

        } catch (\Throwable $th) {
            return json_encode('false');
        }
    }

    public function recuperaArquivo(Request $request)
    {
        $request->validate([
            'hash_arquivo' => 'required',
        ]);

        return $this->getArquivo($request->hash_arquivo);
    }

    public function baixaArquivo(Request $request)
    {
        $request->validate([
            'hash_arquivo' => 'required',
            ]);

        return $this->getArquivo($request->hash_arquivo, true);

    }

    public function urlArquivo(Request $request)
    {
        $request->validate([
            'hash_arquivo' => 'required',
        ]);

        try {
            $hash             = $request->hash_arquivo;
            $arq              =  Arquivo::where('hash', $hash)->first();
            if(!$arq)  return "[ERRO] Arquivo não encontrado.";
            $hash             =  $arq->hash;

            $client           = new Client();
            $url              = env('API_MINIO_URL').'/api/getUrlStorage';
            $result           = $client->request('GET',  $url, [
                'query'       => ['nome_arquivo' => $hash],
                'http_errors' => false
            ]);
            $url = $result->getBody();
            return $url;

        } catch (\Throwable $th) {
            return json_encode('false');
        }
    }
}
