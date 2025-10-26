# Zapii - WhatsApp CRM System

## Visão Geral
Sistema CRM para WhatsApp desenvolvido em Laravel, integrado com Baileys para gerenciamento de conexões WhatsApp.

## Configuração Atual

### Ambiente
- **PHP**: 8.4.10
- **Framework**: Laravel 11.x
- **Banco de Dados**: MySQL Externo (Hostinger)
- **Node.js**: Para serviço Baileys WhatsApp

### Estrutura do Projeto
```
/
├── index.php              # Ponto de entrada principal
├── core/                  # Aplicação Laravel
│   ├── .env              # Configurações de ambiente e banco de dados
│   ├── app/              # Código da aplicação
│   ├── config/           # Arquivos de configuração
│   ├── database/         # Migrations e seeders
│   ├── resources/        # Views e assets
│   ├── routes/           # Definição de rotas
│   └── vendor/           # Dependências PHP
├── baileys-service/      # Serviço Node.js para WhatsApp
└── assets/               # Assets estáticos (CSS, JS, imagens)
```

### Serviços Ativos

#### 1. Laravel Server
- **Porta**: 5000
- **URL**: http://0.0.0.0:5000
- **Status**: ✅ Funcionando
- **Comando**: `php -S 0.0.0.0:5000 -t .`

#### 2. Baileys WhatsApp Service
- **Porta**: 3001
- **URL**: http://127.0.0.1:3001
- **Status**: ✅ Funcionando
- **Comando**: `cd baileys-service && npm start`
- **Sessões Ativas**: Conectado e operacional

### Banco de Dados Externo

**Configuração** (arquivo: `/core/.env`):
- **Tipo**: MySQL
- **Host**: srv1724.hstgr.io
- **Porta**: 3306
- **Database**: u209993987_laravel_teste
- **Status**: ✅ Conectado

### Recursos Principais

1. **Gerenciamento de WhatsApp**
   - Conexão via Baileys (WhatsApp Web API)
   - QR Code para autenticação
   - Múltiplas contas WhatsApp
   - Status de conexão em tempo real

2. **Sistema de Mensagens**
   - Inbox unificado
   - Histórico de conversas
   - Envio e recebimento de mensagens
   - Suporte a mídia (imagens, áudio, documentos)

3. **CRM Features**
   - Gestão de contatos
   - Templates de mensagens
   - Broadcasts
   - Automações

### Integrações Configuradas

- **Pusher**: Broadcasting em tempo real
- **Redis**: Cache e filas (configurado via Predis)
- **Payment Gateways**: Stripe, Razorpay, Mollie, AuthorizeNet, BTCPay, CoinGate
- **SMS**: Twilio, Vonage, MessageBird
- **Email**: SendGrid, Mailjet, PHPMailer

### Dependências Principais

**PHP (Composer)**:
- Laravel Framework 11.x
- Intervention Image (processamento de imagens)
- Laravel Socialite (autenticação social)
- Spatie Laravel Permission (controle de acesso)
- Laravel DomPDF (geração de PDFs)
- PhpSpreadsheet (Excel/CSV)

**Node.js**:
- Baileys (WhatsApp Web API)
- MySQL2 (conexão com banco de dados)

## Comandos Úteis

### Laravel
```bash
cd core

# Limpar cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Migrations
php artisan migrate
php artisan migrate:fresh --seed

# Otimização
php artisan optimize
php artisan config:cache
php artisan route:cache
```

### Composer
```bash
cd core

# Instalar dependências
composer install

# Atualizar dependências
composer update

# Dump autoload
composer dump-autoload
```

### Baileys Service
```bash
cd baileys-service

# Instalar dependências
npm install

# Iniciar serviço
npm start
```

## Notas Importantes

1. **Não alterar a estrutura do sistema** - A estrutura atual está funcionando corretamente
2. **PHP 8.4** - Sistema configurado e testado com PHP 8.4.10
3. **Banco de Dados Externo** - Não implementar banco local, sempre usar o banco externo configurado
4. **index.php na raiz** - O ponto de entrada está em `/index.php`, não em `/core/public/index.php`
5. **Sessões WhatsApp** - Gerenciadas automaticamente pelo serviço Baileys

## Status do Sistema
- ✅ PHP 8.4 instalado e funcionando
- ✅ Dependências instaladas
- ✅ Banco de dados conectado
- ✅ Servidor Laravel rodando (porta 5000)
- ✅ Serviço Baileys rodando (porta 3001)
- ✅ Sistema totalmente operacional

---
**Última Atualização**: 25 de Outubro de 2025
