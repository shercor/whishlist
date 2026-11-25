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
    path: 'registro',
    canActivate: [guestGuard],
    loadComponent: () => import('./features/register/register.page').then((m) => m.RegisterPage),
  },
  {
    // El enlace secreto de una lista. Sin sesión, el guard lo recuerda para después de entrar.
    path: 's/:token',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./features/shared-link/shared-link.page').then((m) => m.SharedLinkPage),
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
  // Buscar personas vive dentro de Gente. La dirección vieja sigue llevando ahí.
  { path: 'personas', redirectTo: 'mi-gente' },
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
  {
    path: 'listas/:id/acceso',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./features/access/wishlist-access.page').then((m) => m.WishlistAccessPage),
  },
  {
    path: 'mi-gente',
    canActivate: [authGuard],
    loadComponent: () => import('./features/follows/follows.page').then((m) => m.FollowsPage),
  },
  {
    path: 'solicitudes',
    canActivate: [authGuard],
    loadComponent: () => import('./features/access/requests.page').then((m) => m.RequestsPage),
  },
  {
    path: 'avisos',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./features/notifications/notifications.page').then((m) => m.NotificationsPage),
  },
  {
    path: 'perfil',
    canActivate: [authGuard],
    loadComponent: () => import('./features/profile/my-profile.page').then((m) => m.MyProfilePage),
  },
  {
    path: '**',
    loadComponent: () => import('./features/not-found/not-found.page').then((m) => m.NotFoundPage),
  },
];
