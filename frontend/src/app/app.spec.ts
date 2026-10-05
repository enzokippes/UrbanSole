import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { App } from './app';
describe('App', () => {
  beforeEach(async () => { await TestBed.configureTestingModule({ imports: [App], providers: [provideRouter([]), provideHttpClient()] }).compileComponents(); });
  it('renders navigation, route outlet and cart without losing the shell', () => {
    const fixture = TestBed.createComponent(App); fixture.detectChanges();
    const html = fixture.nativeElement as HTMLElement;
    expect(html.querySelector('app-navbar')).toBeTruthy(); expect(html.querySelector('router-outlet')).toBeTruthy(); expect(html.querySelector('app-cart-drawer')).toBeTruthy();
  });
});
