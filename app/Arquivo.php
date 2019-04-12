<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class Arquivo extends Model
{
    protected $table = 'files';
    protected $fillable = ['nome', 'hash'];

    public static function getByHash($hash)
    {
        $arq =  Arquivo::where('hash', $hash)->first();
        if(!$arq) {
            throw new \Exception("[ERRO] Arquivo não encontrado.", 412);
        }
        return $arq;
    }

    public static function getByNome($nome)
    {
        $arq =  Arquivo::where('nome', $nome)->first();
        if(!$arq) {
            throw new \Exception("[ERRO] Arquivo não encontrado.", 412);
        }
        return $arq;
    }

    public static function salvaArquivoJson($file)
    {
        $arq_req       = \json_decode($file);
        if($arq_req === null) {
            throw new \Exception("[ERRO] Arquivo json inválido.", 412);
        }
        $arq           = new Arquivo();
        $arq->nome     = $arq_req->nome;
        $arq->hash     = $arq_req->hash_id;
        $arq->extensao = $arq_req->extensao;
        $arq->mimetype = $arq_req->mimetype;
        return $arq->save();
    }
}
