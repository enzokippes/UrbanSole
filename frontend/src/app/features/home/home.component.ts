import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { ProductService } from '../../core/services/product.service';
import { Product } from '../../core/models/product.model';
import { ProductCardComponent } from '../catalog/components/product-card/product-card.component';
import { ModelViewer3dComponent } from '../../shared/components/model-viewer-3d/model-viewer-3d.component';

export interface HeroShowcaseItem {
  id: number;
  category: 'Hombre' | 'Mujer' | 'Niño';
  name: string;
  subtitle: string;
  price: number;
  model3d: string;
  image: string;
}

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, RouterLink, ProductCardComponent, ModelViewer3dComponent],
  templateUrl: './home.component.html',
  styleUrl: './home.component.css'
})
export class HomeComponent implements OnInit {
  private readonly productService = inject(ProductService);

  allProducts = signal<Product[]>([]);
  loading = signal<boolean>(true);
  selectedCategoryTab = signal<string>('Todos');

  heroViewMode = signal<'3d' | 'image'>('3d');
  activeHeroIndex = signal<number>(0);

  readonly heroShowcase: HeroShowcaseItem[] = [
    {
      id: 1,
      category: 'Hombre',
      name: 'Air Jordan 1 High OG "Chicago"',
      subtitle: 'Rojo Varsity · Blanco · Negro',
      price: 185,
      model3d: '/models/jordan-chicago.glb',
      image: '/images/jordan-chicago.jpg'
    },
    {
      id: 5,
      category: 'Mujer',
      name: 'Nike AF1 Shadow "Pastel Dream"',
      subtitle: 'Rosa Pastel · Lavanda · Amarillo',
      price: 155,
      model3d: '/models/af1-pastel-shadow.glb',
      image: '/images/precision-low.jpg'
    },
    {
      id: 10,
      category: 'Niño',
      name: 'Nike Air Max 1 Kids "Citrus Orange"',
      subtitle: 'Blanco Puro · Naranja Cítrico',
      price: 85,
      model3d: '/models/airmax-orange.glb',
      image: '/images/dunk-coast.jpg'
    }
  ];

  readonly currentHeroItem = computed(() => this.heroShowcase[this.activeHeroIndex()]);

  readonly filteredProducts = computed(() => {
    const tab = this.selectedCategoryTab();
    const list = this.allProducts();
    if (tab === 'Todos') return list;
    return list.filter(p => p.category === tab);
  });

  readonly categories = [
    {
      label: 'Hombre',
      query: 'Hombre',
      desc: 'Air Jordan 1 Chicago, AF1 Carhartt Mocha, Air Max 90 Royal & NB 247 Olive',
      count: '4 modelos 3D',
      image: '/images/jordan-chicago.jpg'
    },
    {
      label: 'Mujer',
      query: 'Mujer',
      desc: 'AF1 Shadow Pastel, Velocity Rose Quartz, Jordan 1 Ice White & RS-X Sunset',
      count: '4 modelos 3D',
      image: '/images/precision-low.jpg'
    },
    {
      label: 'Niño',
      query: 'Niño',
      desc: 'Jordan 1 Black Toe Kids, Air Max 1 Citrus, Vans Skate Burgundy & Deerupt Volt',
      count: '4 modelos 3D',
      image: '/images/dunk-coast.jpg'
    }
  ];

  readonly stats = [
    { num: '12', label: 'Modelos 3D Fieles' },
    { num: '360°', label: 'Rotación Interactiva' },
    { num: '3', label: 'Líneas (H · M · Niño)' }
  ];

  ngOnInit(): void {
    this.productService.getProducts({ per_page: 12 }).subscribe({
      next: res => {
        this.allProducts.set(res.data || []);
        this.loading.set(false);
      },
      error: () => {
        this.allProducts.set([]);
        this.loading.set(false);
      }
    });
  }

  selectHeroItem(index: number): void {
    this.activeHeroIndex.set(index);
  }

  setCategoryTab(tab: string): void {
    this.selectedCategoryTab.set(tab);
  }
}
