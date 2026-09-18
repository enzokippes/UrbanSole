import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../../core/services/auth.service';
import { CartService } from '../../../core/services/cart.service';

@Component({
  selector: 'app-navbar',
  standalone: true,
  imports: [CommonModule, RouterLink, FormsModule],
  templateUrl: './navbar.component.html',
  styleUrl: './navbar.component.css'
})
export class NavbarComponent {
  readonly authService = inject(AuthService);
  readonly cartService = inject(CartService);
  private readonly router = inject(Router);

  searchQuery = '';
  mobileMenuOpen = signal(false);
  userMenuOpen = signal(false);

  categories = [
    { label: 'Hombre', query: 'Hombre' },
    { label: 'Mujer', query: 'Mujer' },
    { label: 'Niño', query: 'Niño' }
  ];

  handleSearch(event: Event): void {
    event.preventDefault();
    const query = this.searchQuery.trim();
    if (query) {
      this.router.navigate(['/catalog'], { queryParams: { search: query } });
      this.searchQuery = '';
      this.mobileMenuOpen.set(false);
    }
  }

  logout(): void {
    this.authService.logout().subscribe(() => {
      this.userMenuOpen.set(false);
      this.router.navigate(['/catalog']);
    });
  }

  toggleCart(): void {
    this.cartService.toggleCart();
  }
}
