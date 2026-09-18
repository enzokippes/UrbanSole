import { Routes } from '@angular/router';
import { AuthComponent } from './features/auth/auth.component';
import { CatalogComponent } from './features/catalog/catalog.component';

export const routes: Routes = [
  { path: '', redirectTo: 'catalog', pathMatch: 'full' },
  { path: 'catalog', component: CatalogComponent },
  { path: 'login', component: AuthComponent },
  { path: 'register', component: AuthComponent },
  { path: '**', redirectTo: 'catalog' }
];
