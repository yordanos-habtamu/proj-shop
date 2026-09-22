# Free Production Deployment & CI/CD Guide

This project includes a complete **CI/CD pipeline** via GitHub Actions and is configured for **100% free production deployment** using **Render** (Web Service) and **Neon** (PostgreSQL) or zero-config **SQLite**.

---

## 1. Attach Remote Origin & Push to GitHub

If you haven't already created a GitHub repository, create one at [github.com/new](https://github.com/new) (public or private).

Then in your terminal, run:

```bash
# Add your GitHub repository as remote origin
git remote add origin git@github.com:<YOUR_USERNAME>/<YOUR_REPOSITORY>.git
# OR using HTTPS:
# git remote add origin https://github.com/<YOUR_USERNAME>/<YOUR_REPOSITORY>.git

# Push your main branch to GitHub
git push -u origin main
```

---

## 2. Setting Up Free Hosting (Render + Neon)

### Option A: Web Service on Render (Free Tier)
1. Sign up or log in to [Render](https://render.com/) (free, no credit card required).
2. Click **New +** -> **Web Service**.
3. Connect your GitHub repository.
4. Configure the service:
   - **Name**: `prodhunt` (or your preferred name)
   - **Language / Runtime**: `Docker`
   - **Branch**: `main`
   - **Region**: Choose the region closest to you (e.g., Oregon, Frankfurt)
   - **Instance Type**: **Free** (512 MB RAM, 0.1 CPU)
   - **Auto-Deploy**: Set to **No** (because GitHub Actions CD will trigger deployment *only after* CI tests pass).
5. In **Environment Variables**, add:
   - `APP_NAME`: `Prodhunt`
   - `APP_ENV`: `production`
   - `APP_DEBUG`: `false`
   - `APP_KEY`: Generate one locally with `php artisan key:generate --show` and paste the `base64:...` value.
   - `APP_URL`: Your Render service URL (e.g. `https://prodhunt.onrender.com`).
   - `LOG_CHANNEL`: `stderr`
   - `SESSION_DRIVER`: `database`
   - `QUEUE_CONNECTION`: `database`
   - `CACHE_STORE`: `database`

### Option B: Database Setup (Neon PostgreSQL - Free Forever)
By default, the container automatically initializes a local **SQLite** database. If you want persistent external PostgreSQL that never expires:
1. Create a free account at [Neon.tech](https://neon.tech/) (free 0.5 GiB serverless Postgres).
2. Create a project and copy the connection details.
3. In Render Environment Variables, add:
   - `DB_CONNECTION`: `pgsql`
   - `DB_HOST`: `<neon-host>`
   - `DB_PORT`: `5432`
   - `DB_DATABASE`: `<neon-dbname>`
   - `DB_USERNAME`: `<neon-username>`
   - `DB_PASSWORD`: `<neon-password>`
   - `DB_SSLMODE`: `require`

---

## 3. Enable Automated Continuous Deployment (CD)

1. In your **Render Web Service** dashboard:
   - Go to **Settings** -> scroll down to **Deploy Hook**.
   - Copy the Deploy Hook URL (it looks like `https://api.render.com/deploy/srv-xxxx?key=yyyy`).
2. In your **GitHub Repository**:
   - Go to **Settings** -> **Secrets and variables** -> **Actions**.
   - Click **New repository secret**.
   - **Name**: `RENDER_DEPLOY_HOOK`
   - **Secret**: Paste your Render Deploy Hook URL.
   - Click **Add secret**.

---

## 4. How the CI/CD Pipeline Works

Every time you push commits to `main` or create a pull request:

```
                  ┌───────────────────────────────┐
                  │          git push             │
                  └──────────────┬────────────────┘
                                 │
                                 ▼
                  ┌───────────────────────────────┐
                  │    GitHub Actions CI Job      │
                  ├───────────────────────────────┤
                  │ • PHP 8.3 & Node 22 Setup     │
                  │ • Pint Code Styling Check     │
                  │ • PHPStan Type Analysis       │
                  │ • TypeScript Compilation Check│
                  │ • Feature & Unit Tests        │
                  │ • Vite Asset Build Check      │
                  └──────────────┬────────────────┘
                                 │
                        (on main & CI success)
                                 │
                                 ▼
                  ┌───────────────────────────────┐
                  │    GitHub Actions CD Job      │
                  ├───────────────────────────────┤
                  │ • Triggers RENDER_DEPLOY_HOOK │
                  └──────────────┬────────────────┘
                                 │
                                 ▼
                  ┌───────────────────────────────┐
                  │      Render Docker Build      │
                  ├───────────────────────────────┤
                  │ • Builds Multi-Stage Image    │
                  │ • Runs Database Migrations    │
                  │ • Caches Config, Routes, Views│
                  │ • Serves via FrankenPHP       │
                  └───────────────────────────────┘
```

---

## Alternative: Deploying to Koyeb (Free Eco Tier)
If you prefer [Koyeb](https://www.koyeb.com/):
1. Create a free Eco instance.
2. Select **GitHub** deployment and pick your repository.
3. Koyeb will automatically detect the `Dockerfile`.
4. Copy the Koyeb deploy hook into GitHub repository secret `DEPLOY_HOOK_URL`.
