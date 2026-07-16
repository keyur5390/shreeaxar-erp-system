<?php
return ['stateful'=>explode(',', env('SANCTUM_STATEFUL_DOMAINS','localhost:5174')),'guard'=>['web'],'expiration'=>null,'middleware'=>['encrypt_cookies'=>Illuminate\Cookie\Middleware\EncryptCookies::class,'validate_csrf_token'=>Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]];
