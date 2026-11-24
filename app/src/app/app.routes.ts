import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/auth/auth.guards';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'listas' },
  {
    path: 'login',
    canActivate: [guestGuard],
    loadComponent: () => import('./features/login/login.page').then((m) => m.LoginPage),
  },
  {
    path: 'listas',
    canActivate: [authGuard],
    loadComponent: () => import('./features/wishlists/wishlists.page').then((m) => m.WishlistsPage),
  },
  {
    path: 'listas/nueva',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./features/wishlists/wishlist-form.page').then((m) => m.WishlistFormPage),
  },
  {
    path: 'listas/:id',
    canActivate: [authGuard],
    loadComponent: () => import('./features/wishlists/wishlist.page').then((m) => m.WishlistPage),
  },
  {
    path: 'listas/:id/editar',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./features/wishlists/wishlist-form.page').then((m) => m.WishlistFormPage),
  },
  {
    path: 'listas/:id/regalos/nuevo',
    canActivate: [authGuard],
    loadComponent: () => import('./features/items/item-form.page').then((m) => m.ItemFormPage),
  },
  { path: '**', redirectTo: 'listas' },
];
