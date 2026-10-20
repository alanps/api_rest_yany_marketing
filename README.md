# API REST Yany Marketing

API REST para cadastro e autenticação de usuários, construída com Laravel 13 e Laravel Sanctum. Os endpoints de API ficam em `routes/api.php` e recebem o prefixo `/api` automaticamente.

## Requisitos

O ambiente DDEV deste projeto usa:

- PHP 8.4 (o projeto requer PHP 8.3 ou superior);
- MariaDB 11.8;
- Node.js 24 para compilar os assets do frontend;
- Docker Desktop e DDEV instalados.

## Instalação

Na primeira instalação, copie o arquivo de ambiente de exemplo. No PowerShell:

```powershell
Copy-Item .env.example .env
```

Configure o `.env` para usar o banco fornecido pelo DDEV:

```dotenv
APP_URL=https://api-rest-yany-marketing.ddev.site
DB_CONNECTION=mariadb
DB_HOST=db
DB_PORT=3306
DB_DATABASE=db
DB_USERNAME=db
DB_PASSWORD=db
```

Inicie o ambiente e instale as dependências:

```bash
ddev start
ddev composer install
ddev npm install
ddev exec php artisan key:generate
ddev exec php artisan migrate
ddev npm run build
```

O comando de migração cria as tabelas da aplicação e a tabela `personal_access_tokens` usada pelo Sanctum. O `.env` contém configurações locais e não deve ser enviado ao repositório.

## Execução

O DDEV serve a aplicação em:

```text
https://api-rest-yany-marketing.ddev.site
```

Para inspecionar as rotas da API:

```bash
ddev exec php artisan route:list --path=api
```

Para executar os testes:

```bash
ddev exec php artisan test --compact
```

## Autenticação

O cadastro e o login retornam um token pessoal do Sanctum. Envie-o nas rotas protegidas pelo cabeçalho `Authorization: Bearer <token>`. Inclua também `Accept: application/json` e, ao enviar JSON, `Content-Type: application/json`.

Guarde o token com segurança. Ele é retornado ao criar a sessão; depois do logout, o token revogado deixa de autenticar requisições.

Nos exemplos abaixo, substitua `https://api-rest-yany-marketing.ddev.site` pela URL do seu ambiente, se necessário.

## Rotas

| Método | Rota | Autenticação | Descrição |
| --- | --- | --- | --- |
| `POST` | `/api/register` | Não | Cria um usuário e retorna um token |
| `POST` | `/api/login` | Não | Valida as credenciais e retorna um token |
| `GET` | `/api/me` | Bearer token | Retorna o usuário autenticado |
| `POST` | `/api/logout` | Bearer token | Revoga o token usado na requisição |
| `POST` | `/api/logout-all` | Bearer token | Revoga todos os tokens do usuário |

### Cadastro — `POST /api/register`

Campos obrigatórios: `name`, `email`, `password` e `password_confirmation`. A senha precisa ter pelo menos 8 caracteres e corresponder à confirmação.

```bash
curl -X POST https://api-rest-yany-marketing.ddev.site/api/register \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Ana Silva",
    "email": "ana@example.com",
    "password": "senha-segura-123",
    "password_confirmation": "senha-segura-123"
  }'
```

Resposta `201 Created`:

```json
{
  "user": {
    "name": "Ana Silva",
    "email": "ana@example.com",
    "updated_at": "2026-10-20T12:00:00.000000Z",
    "created_at": "2026-10-20T12:00:00.000000Z",
    "id": 1
  },
  "token": "1|token-de-exemplo"
}
```

O campo `password` não é incluído na resposta. E-mail duplicado ou outros erros de validação retornam `422 Unprocessable Entity`.

### Login — `POST /api/login`

```bash
curl -X POST https://api-rest-yany-marketing.ddev.site/api/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "ana@example.com",
    "password": "senha-segura-123"
  }'
```

Resposta `200 OK` contém `user` e `token`. Credenciais incorretas retornam `401 Unauthorized`; campos ausentes ou inválidos retornam `422`. O endpoint permite até cinco tentativas por minuto para a combinação de e-mail e endereço IP; tentativas excedentes recebem `429 Too Many Requests`.

### Consultar usuário autenticado — `GET /api/me`

```bash
curl https://api-rest-yany-marketing.ddev.site/api/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer SEU_TOKEN"
```

Retorna os dados do usuário autenticado. Sem token ou com token inválido, retorna `401 Unauthorized`.

### Logout — `POST /api/logout`

Revoga somente o token enviado na requisição:

```bash
curl -X POST https://api-rest-yany-marketing.ddev.site/api/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer SEU_TOKEN"
```

Retorna `200 OK` com uma mensagem de confirmação. O token revogado não poderá ser usado novamente.

### Logout de todos os dispositivos — `POST /api/logout-all`

Revoga todos os tokens do usuário autenticado, inclusive o token usado nesta chamada:

```bash
curl -X POST https://api-rest-yany-marketing.ddev.site/api/logout-all \
  -H "Accept: application/json" \
  -H "Authorization: Bearer SEU_TOKEN"
```

Após essa chamada, será necessário fazer login novamente para obter outro token.

## Formato dos erros

Erros de validação retornam `422` com a mensagem e os detalhes por campo, por exemplo:

```json
{
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Credenciais inválidas e acesso sem autenticação retornam `401`. Envie `Accept: application/json` para receber respostas no formato JSON.
