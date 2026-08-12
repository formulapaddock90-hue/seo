#!/usr/bin/env python3
"""
GitHub Uploader Script
----------------------
Carica facilmente una qualsiasi cartella locale su un repository GitHub esistente.
Includendo la funzionalità di AZZERAMENTO/PULIZIA TOTALE del repository remoto per
cancellare tutti i vecchi file e commit per lasciare SOLO il nuovo contenuto della cartella.
"""

import os
import sys
import subprocess
import argparse
from pathlib import Path

MAX_FILE_SIZE_MB = 100

DEFAULT_GITIGNORE_CONTENT = """# File e cartelle ignorate generate automaticamente da github_uploader.py
.env
*.log
__pycache__/
*.py[cod]
*$py.class
node_modules/
dist/
build/
.idea/
.vscode/
.DS_Store
Thumbs.db
*.exe
"""

def run_command(cmd, cwd=None, check=True, capture_output=True):
    """Esegue un comando shell e restituisce il risultato."""
    try:
        result = subprocess.run(
            cmd,
            cwd=cwd,
            check=check,
            text=True,
            capture_output=capture_output
        )
        return result.returncode == 0, result.stdout.strip(), result.stderr.strip()
    except subprocess.CalledProcessError as e:
        return False, e.stdout.strip() if e.stdout else "", e.stderr.strip() if e.stderr else str(e)
    except FileNotFoundError:
        return False, "", f"Comando non trovato: {cmd[0]}"

def check_git_installed():
    """Verifica che Git sia installato nel sistema."""
    success, stdout, _ = run_command(["git", "--version"])
    if not success:
        print("[ERRORE] Git non è installato o non è presente nel PATH del sistema.")
        print("Installa Git prima di eseguire questo script: https://git-scm.com/")
        sys.exit(1)
    print(f"[OK] {stdout}")

def sanitize_path(raw_path):
    """Pulisce e converte un percorso stringa in un oggetto Path valido."""
    cleaned = raw_path.strip().strip('"').strip("'")
    return Path(cleaned).expanduser().resolve()

def setup_gitignore(folder_path):
    """Crea o aggiorna il file .gitignore nella cartella locale."""
    gitignore_path = folder_path / ".gitignore"
    if not gitignore_path.exists():
        print("[INFO] Creazione del file .gitignore di base...")
        with open(gitignore_path, "w", encoding="utf-8") as f:
            f.write(DEFAULT_GITIGNORE_CONTENT)
        print("[OK] File .gitignore creato.")
    else:
        print("[INFO] File .gitignore già esistente.")

def scan_large_files(folder_path):
    """Controlla se ci sono file superiori a MAX_FILE_SIZE_MB nella cartella."""
    large_files = []
    print("[INFO] Scansione dei file di grandi dimensioni (>100MB)...")
    
    for root, dirs, files in os.walk(folder_path):
        if ".git" in root:
            continue
        for file in files:
            file_path = Path(root) / file
            try:
                size_mb = file_path.stat().st_size / (1024 * 1024)
                if size_mb > MAX_FILE_SIZE_MB:
                    rel_path = file_path.relative_to(folder_path)
                    large_files.append((str(rel_path), size_mb))
            except Exception:
                pass

    if large_files:
        print(f"\n[ATTENZIONE] Trovati {len(large_files)} file superiori al limite di {MAX_FILE_SIZE_MB}MB di GitHub:")
        for rel_p, size in large_files:
            print(f"  - {rel_p} ({size:.2f} MB)")
        
        answer = input("\nVuoi aggiungere questi file al .gitignore per evitare che il push fallisca? (S/n): ").strip().lower()
        if answer in ["", "s", "si", "y", "yes"]:
            gitignore_path = folder_path / ".gitignore"
            with open(gitignore_path, "a", encoding="utf-8") as f:
                f.write("\n# File grandi esclusi automaticamente\n")
                for rel_p, _ in large_files:
                    f.write(f"/{rel_p.replace(os.sep, '/')}\n")
            print("[OK] File di grandi dimensioni aggiunti a .gitignore.")
    else:
        print("[OK] Nessun file oltre i 100MB rilevato.")

def check_remote_is_not_empty(folder_path, branch):
    """Verifica se il repository remoto contiene già dei commit o branch."""
    ok, stdout, _ = run_command(["git", "ls-remote", "--heads", "origin"], cwd=folder_path)
    if ok and stdout:
        return True
    return False

def purge_and_reset_git(folder_path, branch, commit_message):
    """Azzera lo storico locale Git e crea un branch pulito da zero."""
    print("[INFO] Resettando il branch locale per un caricamento totalmente da zero...")
    temp_branch = "temp_clean_purge"
    run_command(["git", "checkout", "--orphan", temp_branch], cwd=folder_path)
    run_command(["git", "add", "."], cwd=folder_path)
    run_command(["git", "commit", "-m", commit_message], cwd=folder_path)
    run_command(["git", "branch", "-D", branch], cwd=folder_path)
    run_command(["git", "branch", "-m", branch], cwd=folder_path)
    print("[OK] Branch locale resettato a un singolo commit pulito.")

def upload_to_github(folder_path, repo_url, branch="main", commit_message="Caricamento pulito da script Python", force=False, wipe_remote=False):
    """Esegue il flusso completo di caricamento Git su GitHub."""
    print(f"\n=== Avvio caricamento per: {folder_path} ===")
    
    # 1. Inizializzazione Git
    git_dir = folder_path / ".git"
    if not git_dir.exists():
        print("[INFO] Inizializzazione repository Git locale...")
        ok, out, err = run_command(["git", "init"], cwd=folder_path)
        if not ok:
            print(f"[ERRORE] Inizializzazione fallita: {err}")
            return False
        print("[OK] Repository Git inizializzato.")

    # 2. Configurazione del Remote Origin
    ok, remotes, _ = run_command(["git", "remote"], cwd=folder_path)
    if "origin" in remotes.split():
        print(f"[INFO] Aggiornamento URL remote 'origin' in: {repo_url}")
        run_command(["git", "remote", "set-url", "origin", repo_url], cwd=folder_path)
    else:
        print(f"[INFO] Aggiunta del remote 'origin': {repo_url}")
        run_command(["git", "remote", "add", "origin", repo_url], cwd=folder_path)

    # 3. Controllo o azzeramento repository remoto
    remote_has_content = check_remote_is_not_empty(folder_path, branch)
    
    if not force and not wipe_remote and remote_has_content:
        print("\n----------------------------------------------------------------------")
        print("[ATTENZIONE] Il repository remoto su GitHub contiene già dei file/commit.")
        print("----------------------------------------------------------------------")
        print("Opzioni disponibili:")
        print("  1. AZZERARE E CANCELLARE TUTTO su GitHub (sostituisce tutto con questa cartella)")
        print("  2. Unire le modifiche (normal push)")
        answer = input("\nVuoi CANCELLARE TUTTO su GitHub prima di caricare la cartella? (S/n): ").strip().lower()
        if answer in ["", "s", "si", "y", "yes"]:
            wipe_remote = True
            force = True

    if wipe_remote:
        print("\n[INFO] AZZERAMENTO IN CORSO: reset del codice per eliminare ogni vecchio file remoto...")
        purge_and_reset_git(folder_path, branch, commit_message)
        force = True

    # 4. Configurazione o verifica del Branch
    run_command(["git", "checkout", "-B", branch], cwd=folder_path)

    # 5. Aggiunta dei file e Commit (se non azzerato al punto 3)
    if not wipe_remote:
        print("[INFO] Aggiunta dei file allo staging area (`git add .`)...")
        run_command(["git", "add", "."], cwd=folder_path)
        ok, status_out, _ = run_command(["git", "status", "--porcelain"], cwd=folder_path)
        if status_out:
            print(f"[INFO] Creazione del commit: '{commit_message}'...")
            run_command(["git", "commit", "-m", commit_message], cwd=folder_path)

    # 6. Push forzato su GitHub per sovrascrivere/azzerare la repo remota
    print(f"[INFO] Invio file su GitHub (git push -u origin {branch}{' --force' if force else ''})...")
    push_cmd = ["git", "push", "-u", "origin", branch]
    if force:
        push_cmd.append("--force")

    ok, out, err = run_command(push_cmd, cwd=folder_path)
    if ok:
        print("\n==================================================")
        print(" [SUCCESS] CARICAMENTO COMPLETATO CON SUCCESSO! ")
        print(f" Repository: {repo_url}")
        if force or wipe_remote:
            print(" [INFO] IL REPOSITORY REMOTO É STATO COMPLETAMENTE AZZERATO E SOSTITUITO!")
        print("==================================================\n")
        return True
    else:
        print(f"\n[ERRORE] Il push è fallito.")
        print(f"Dettagli errore: {err}")
        if "secret" in err.lower() or "rule violation" in err.lower():
            print("\n[ERRORE SICUREZZA GITHUB] Push bloccato da GitHub Secret Scanning!")
            print("Uno o più file contengono chiavi API o credenziali in chiaro (es. Anthropic, OpenAI, Gemini).")
            print("Rimuovi o maschera le chiavi nei file sorgente prima di effettuare il push.")
        elif "non-fast-forward" in err or "fetch first" in err or "behind" in err:
            print("\n[SUGGERIMENTO] Il repository remoto contiene già dei file non presenti in locale.")
            retry_force = input("Vuoi AZZERARE e cancellare il repository remoto forzando il push? (s/N): ").strip().lower()
            if retry_force in ["s", "si", "y", "yes"]:
                return upload_to_github(folder_path, repo_url, branch, commit_message, force=True, wipe_remote=True)
        return False

def main():
    parser = argparse.ArgumentParser(description="Carica una cartella locale su un repository GitHub azzerandolo se richiesto.")
    parser.add_argument("--folder", "-f", help="Percorso della cartella locale da caricare.")
    parser.add_argument("--repo", "-r", help="URL del repository GitHub (es. https://github.com/utente/repo.git).")
    parser.add_argument("--branch", "-b", default="main", help="Nome del branch remoto (default: main).")
    parser.add_argument("--message", "-m", default="Upload cartella da script Python", help="Messaggio del commit.")
    parser.add_argument("--wipe", "--clean", "--force", action="store_true", help="Azzera e cancella tutti i vecchi file/commit remoti prima di caricare.")

    args = parser.parse_args()

    print("==================================================")
    print("        GitHub Folder Uploader - Python Tool      ")
    print("==================================================")

    check_git_installed()

    # Gestione percorso cartella
    folder_str = args.folder
    if not folder_str:
        folder_str = input("\nInserisci il percorso della cartella da caricare (premi Invio per la cartella corrente): ").strip()
        if not folder_str:
            folder_str = "."

    folder_path = sanitize_path(folder_str)

    if not folder_path.exists() or not folder_path.is_dir():
        print(f"[ERRORE] La cartella '{folder_path}' non esiste o non è una directory valida.")
        sys.exit(1)

    print(f"[OK] Cartella selezionata: {folder_path}")

    # Gestione URL repository
    repo_url = args.repo
    if not repo_url:
        git_dir = folder_path / ".git"
        existing_url = ""
        if git_dir.exists():
            ok, out, _ = run_command(["git", "remote", "get-url", "origin"], cwd=folder_path)
            if ok and out:
                existing_url = out.strip()
        
        prompt_msg = f"\nInserisci l'URL del repository GitHub (es. https://github.com/user/repo.git)"
        if existing_url:
            prompt_msg += f" [{existing_url}]: "
        else:
            prompt_msg += ": "
            
        entered_url = input(prompt_msg).strip()
        repo_url = entered_url if entered_url else existing_url

    if not repo_url:
        print("[ERRORE] L'URL del repository GitHub è obbligatorio.")
        sys.exit(1)

    # Gestione messaggio commit
    commit_msg = args.message
    if not args.message or args.message == "Upload cartella da script Python":
        user_msg = input(f"Messaggio del commit [{commit_msg}]: ").strip()
        if user_msg:
            commit_msg = user_msg

    # Setup e controlli preliminari
    setup_gitignore(folder_path)
    scan_large_files(folder_path)

    # Esecuzione upload
    upload_to_github(
        folder_path=folder_path,
        repo_url=repo_url,
        branch=args.branch,
        commit_message=commit_msg,
        force=args.wipe,
        wipe_remote=args.wipe
    )

if __name__ == "__main__":
    main()
