import { Routes } from '@angular/router';
import { AuthComponent } from './features/auth/auth.component';

export const routes: Routes = [
  { path: 'login', component: AuthComponent },
  { path: 'register', component: AuthComponent },
  { path: '', redirectTo: 'login', pathMatch: 'full' },
  { path: '**', redirectTo: 'login' }
];
