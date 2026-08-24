# UrbanSole

A modern sneaker e-commerce web application featuring real-time 3D product visualization, dynamic colorway asset switching, and a persistent dark/light theme architecture.

Built with a decoupled full-stack architecture: Laravel 11 REST API (PHP, SQLite, Laravel Sanctum) on the backend and React 18, Vite, Tailwind CSS, and the Google `<model-viewer>` component on the frontend.

> **Notice:** Brand names, silhouettes, and trademarks referenced in this repository are used exclusively for internal demonstration, technical prototyping, and portfolio evaluation purposes.

---

## Key Features

### 3D Model Visualization
- Interactive 360-degree orbital rotation, responsive camera angle adjustments, and smooth zoom controls powered by the Google `<model-viewer>` Web Component.
- Physically Based Rendering (PBR) metallic-roughness material workflow.
- Self-hosted binary glTF (`.glb`) assets optimized for fast initial rendering.

### Dynamic Colorway Asset Switching
- Real-time image and asset synchronization upon selecting color variants across both product detail pages and catalog cards.
- Integrated colorway gallery with responsive thumbnail indicators.

### Persistent Dark and Light Modes
- Dynamic theme switching between Dark (Obsidian) and Light (Porcelain Minimalist) modes.
- Persistent user preference saved in local storage with automatic system preference detection.

### E-Commerce Workflows
- Filterable and searchable product catalog categorized by Men, Women, and Kids.
- Variant inventory validation with live stock counts per size and color.
- Persistent client-side cart drawer synchronized with user session state.
- Simulated checkout and order placement pipeline.

### Authentication and Access Control
- Token-based API authentication powered by Laravel Sanctum.
- Role-based route guards distinguishing standard customers from administrators.
- Administrative dashboard with store revenue, order management, and stock oversight.

---

## Technical Stack

### Frontend
- **Framework:** React 18
- **Build Tooling:** Vite 8
- **Styling:** Tailwind CSS v4
- **3D Rendering:** Google `<model-viewer>` Web Component
- **Icons:** Lucide React
- **HTTP Client:** Axios
- **Routing:** React Router v6

### Backend
- **Framework:** Laravel 11
- **Runtime:** PHP 8.2+
- **Database:** SQLite
- **Authentication:** Laravel Sanctum (Bearer Token)
- **Design Pattern:** RESTful Controllers, Eloquent ORM, Database Seeders

---

## Architecture Overview

```text
UrbanSole/
├── backend/                  # Laravel 11 API service
│   ├── app/
│   │   ├── Http/Controllers/ # REST API controllers (Auth, Product, Cart, Order, Admin)
│   │   ├── Http/Middleware/  # Sanctum and role-based guards
│   │   └── Models/           # Eloquent entities (User, Product, Variant, CartItem, Order)
│   ├── database/
│   │   ├── migrations/       # Schema definitions
│   │   └── seeders/          # Seed data (Products, Variants, Demo Users)
│   ├── routes/               # API endpoint definitions
│   └── public/
│       ├── images/           # Product colorway photography
│       └── models/           # Binary 3D assets (.glb)
│
├── frontend/                 # React 18 SPA
│   ├── src/
│   │   ├── api/              # Axios service endpoints
│   │   ├── components/       # Reusable UI modules (Navbar, CartSidebar, ModelViewer3D, ProductCard)
│   │   ├── context/          # Context providers (AuthContext, CartContext, ThemeContext)
│   │   ├── pages/            # View components (Home, Catalog, Product, Checkout, Orders, Auth, Admin)
│   │   └── index.css         # Global design tokens and theme rules
│   └── public/
│       ├── images/           # Static product images
│       └── models/           # Static 3D model files
│
├── iniciar.bat               # Windows batch launcher for concurrent server startup
├── .gitignore                # Global version control exclusion rules
└── README.md                 # Project documentation
```

---

## Getting Started

### System Requirements
- **Node.js**: v18.0.0 or later
- **PHP**: v8.2 or later with extensions enabled: `pdo_sqlite`, `sqlite3`, `openssl`, `mbstring`, `fileinfo`, `curl`
- **Composer**: v2.x

---

### Installation

#### 1. Clone the repository

```bash
git clone https://github.com/enzokippes/UrbanSole.git
cd UrbanSole
```

#### 2. Backend Setup

```bash
# Navigate to backend directory
cd backend

# Install PHP dependencies
composer install

# Configure environment file
cp .env.example .env

# Generate application encryption key
php artisan key:generate

# Initialize the SQLite database file
touch database/database.sqlite
# (Windows PowerShell alternative: New-Item -Path "database/database.sqlite" -ItemType File -Force)

# Execute database migrations and seed default data
php artisan migrate --seed

# Start the Laravel local development server
php artisan serve --host=127.0.0.1 --port=8000
```

The backend API service will be accessible at `http://127.0.0.1:8000`.

#### 3. Frontend Setup

In a separate terminal window:

```bash
# Navigate to frontend directory
cd frontend

# Install Node dependencies
npm install

# Start the Vite development server
npm run dev
```

The web application will be accessible at `http://localhost:5173`.

---

### Windows Quick Launch

To start both the backend API and frontend dev server simultaneously in dedicated console windows:

```cmd
iniciar.bat
```

---

## Demonstration Credentials

| Role | Email | Password | Permissions |
|---|---|---|---|
| Administrator | admin@urbansole.com | password | Administrative dashboard, metrics, and stock control |
| Customer | demo@urbansole.com | password | Standard shopping, cart management, and checkout |

---

## API Endpoints Reference

| HTTP Method | Route | Description | Authorization |
|---|---|---|---|
| `GET` | `/api/products` | Paginated product list with search and attribute filters | Public |
| `GET` | `/api/products/{slug}` | Detailed product data with variants and 3D asset URL | Public |
| `GET` | `/api/categories` | Available product categories | Public |
| `POST` | `/api/auth/register` | Register a new user account | Public |
| `POST` | `/api/auth/login` | Authenticate credentials and return access token | Public |
| `POST` | `/api/auth/logout` | Invalidate current session token | Bearer Token |
| `GET` | `/api/cart` | Retrieve current user shopping cart items | Bearer Token |
| `POST` | `/api/cart` | Add product variant item to cart | Bearer Token |
| `PUT` | `/api/cart/{id}` | Update quantity of a cart item | Bearer Token |
| `DELETE` | `/api/cart/{id}` | Remove an item from the cart | Bearer Token |
| `GET` | `/api/orders` | Retrieve authenticated user order history | Bearer Token |
| `POST` | `/api/orders` | Process and finalize an order from current cart | Bearer Token |
| `GET` | `/api/admin/stats` | Access store revenue, order volume, and inventory statistics | Administrator |

---

## 3D Asset Specifications

- **Format:** Binary glTF (`.glb`)
- **Shading Model:** Physically Based Rendering (PBR)
- **Viewport Controls:** Orbit rotation, auto-rotation, zoom constraints, and bounding box normalization
- **Rendering Engine:** WebGL 2.0 / WebXR via Google `<model-viewer>`

---

## License

This project is open-source and distributed under the [MIT License](LICENSE).
