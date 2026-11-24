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
    // La misma pantalla para una lista ajena, con otra ruta: el menú no marca «Mis listas».
    path: 'regalar/:id',
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
  {
    path: 'descubrir',
    canActivate: [authGuard],
    loadComponent: () => import('./features/discover/discover.page').then((m) => m.DiscoverPage),
  },
  {
    path: 'personas',
    canActivate: [authGuard],
    loadComponent: () => import('./features/people/people.page').then((m) => m.PeoplePage),
  },
  {
    path: 'u/:username',
    canActivate: [authGuard],
    loadComponent: () => import('./features/people/profile.page').then((m) => m.ProfilePage),
  },
  {
    path: 'reservas',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./features/reservations/reservations.page').then((m) => m.ReservationsPage),
  },
  { path: '**', redirectTo: 'listas' },
];
