# Generatore Contenuti Social F1

App PHP che, dato un **testo** o un **URL**, genera automaticamente:

- Testo post **Facebook**
- Testo **Twitter/X** (+ una variante "Twitter modificato")
- Testo **LinkedIn**
- **Categoria** dell'argomento
- **Infografica Facebook** (1200x630 jpg)
- **Infografica Instagram** (1080x1080 jpg)
- **Reel verticale** (mp4 1080x1920, generato da ffmpeg con effetto zoom + testo)

Tutto viene salvato:
- I **testi** + i **link** alle immagini/reel su **Google Sheets**
- Le **immagini** e i **reel** su **Google Drive**

---

## 1. Requisiti

- PHP >= 8.0 con estensioni: `gd`, `curl`, `dom`, `mbstring`, `openssl`
  (tutte incluse in XAMPP; verifica che `extension=gd` sia abilitata in `php.ini`)
- **Nessuna dipendenza Composer**: le API Google sono chiamate via REST/cURL puro
- **ffmpeg** installato sul server (per generare il Reel). Su Ubuntu/Debian:
  ```
  sudo apt install ffmpeg
  ```
- Una chiave API **Gemini** (https://aistudio.google.com/apikey) oppure **Anthropic/Claude**
  (https://console.anthropic.com/) — il provider si sceglie con `ai_provider` in `config.php`
- Un progetto **Google Cloud** con:
  - Google Sheets API attivata
  - Google Drive API attivata
  - Un **Service Account** con relativo file JSON delle credenziali (per Sheets)
  - Un **ID client OAuth** di tipo "Applicazione web" (per l'upload su Drive — vedi sotto)

> ⚠️ **Upload su Drive**: i Service Account NON hanno quota di archiviazione su Google Drive,
> quindi l'upload dei file avviene tramite **OAuth utente**: una autorizzazione una-tantum
> via browser (`oauth_setup.php`), dopo la quale i file vengono caricati a nome del tuo
> account con rinnovo automatico del token.

---

## 2. Installazione

Nessuna installazione di librerie: copia la cartella del progetto nella webroot
(es. `C:\xampp\htdocs\social`) e configura `config.php`. Le API Google (Sheets/Drive)
e Gemini/Claude sono chiamate direttamente via cURL, senza Composer.

---

## 3. Configurazione Google (Service Account)

1. Vai su [Google Cloud Console](https://console.cloud.google.com/) > crea/seleziona un progetto.
2. Attiva **Google Sheets API** e **Google Drive API** (menu "API e servizi" > "Libreria").
3. Vai su "IAM e amministrazione" > "Account di servizio" > **Crea account di servizio**.
4. Una volta creato, vai su "Chiavi" > "Aggiungi chiave" > **Crea nuova chiave** > formato **JSON**.
5. Scarica il file JSON e salvalo come:
   ```
   social-generator/credentials/service-account.json
   ```
6. **Condividi il Google Sheet** con l'email del service account (es. `nome@progetto.iam.gserviceaccount.com`),
   dandogli ruolo **Editor**.
   Sheet: https://docs.google.com/spreadsheets/d/1YSrwn0wcmxIQzucRoe2SmSWFWHIUAr0u624aMCY-wzE/edit
7. Crea una cartella su **Google Drive** dove salvare immagini/reel, e **condividila** con la stessa email
   del service account (ruolo Editor). Copia l'ID della cartella dall'URL
   (es. `https://drive.google.com/drive/folders/QUESTO_E_L_ID`).

### OAuth per l'upload su Drive (obbligatorio per salvare immagini/reel)

1. Su Google Cloud Console vai su "API e servizi" > "Schermata consenso OAuth": configurala
   (tipo **Esterno**) e aggiungi il tuo indirizzo Gmail tra gli **utenti di test**.
2. Vai su "Credenziali" > "Crea credenziali" > **ID client OAuth** > tipo **Applicazione web**.
3. In "URI di reindirizzamento autorizzati" aggiungi esattamente:
   ```
   http://localhost/social/oauth_setup.php
   ```
4. Scarica il JSON del client e salvalo come `credentials/oauth-client.json`.
5. Apri `http://localhost/social/oauth_setup.php` nel browser e autorizza
   l'account Google **proprietario della cartella Drive**.
6. Il token viene salvato in `credentials/oauth-token.json` e rinnovato in automatico.

---

## 4. Configurazione dell'app

Apri `config.php` e compila:

```php
'anthropic_api_key' => 'sk-ant-...',          // la tua chiave Anthropic
'google_drive_folder_id' => 'ID_CARTELLA',    // ID cartella Drive creata al punto 3.7
'google_sheet_tab' => '',                     // lascia vuoto = primo foglio (gid=0)
'ffmpeg_path' => 'ffmpeg',                    // o percorso assoluto, es. /usr/bin/ffmpeg
```

L'ID del foglio Google Sheets e' GIA' impostato correttamente:
`1YSrwn0wcmxIQzucRoe2SmSWFWHIUAr0u624aMCY-wzE`

### Colonne del Google Sheet (ordine richiesto)

| data | facebook | twitter | linkedin | instagram | categoria | img evidenza | twitter modificato | link |
|------|----------|---------|----------|-----------|-----------|--------------|---------------------|------|

L'app scrive automaticamente in questo ordine, una riga per ogni contenuto generato.

---

## 5. Font per le infografiche (opzionale ma consigliato)

Per un risultato grafico migliore, scarica i font **Montserrat Bold** e **Montserrat Regular**
(gratuiti su Google Fonts) e salvali in:

```
social-generator/fonts/Montserrat-Bold.ttf
social-generator/fonts/Montserrat-Regular.ttf
```

Se i font non sono presenti, l'app usa comunque i font interni di GD come fallback
(qualita' grafica inferiore).

---

## 6. Avvio

Con il server PHP integrato (solo per test):

```bash
php -S localhost:8000 -t social-generator
```

Apri il browser su `http://localhost:8000/index.php`, inserisci un testo o un URL
e premi "Genera contenuti".

In produzione, copia la cartella `social-generator` nella webroot del tuo hosting
(assicurati che le cartelle `output/images`, `output/reels` e `credentials` siano
scrivibili dal webserver e NON pubblicamente accessibili/indicizzabili).

---

## 7. Note di sicurezza

- **Non** mettere mai `credentials/service-account.json` o le chiavi API in repository pubblici.
- Limita l'accesso pubblico alla cartella `credentials/` (es. tramite `.htaccess`):
  ```
  Deny from all
  ```
- Considera di proteggere `index.php`/`process.php` con autenticazione, se l'app
  e' esposta su internet, per evitare un uso non autorizzato (consumo di crediti API).

---

## 8. Personalizzazioni rapide

- **Stile infografiche**: modifica `includes/image_generator.php` (colori, layout, font).
- **Durata/stile reel**: modifica `includes/video_generator.php`
  (parametro `$durationSeconds`, filtro `zoompan`, dimensione testo).
- **Prompt AI / tono dei testi**: modifica il prompt di sistema in
  `includes/ai_generator.php` (funzione `generateSocialContent`).
- **Mapping colonne Google Sheet**: `includes/google_service.php`,
  funzione `appendRowToSheet`.
