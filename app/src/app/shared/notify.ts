import { Injectable, inject } from '@angular/core';
import { AlertController, ToastController } from '@ionic/angular';

/** Avisos flotantes y confirmaciones, con el mismo aspecto en toda la app. */
@Injectable({ providedIn: 'root' })
export class Notify {
  private readonly toasts = inject(ToastController);
  private readonly alerts = inject(AlertController);

  async toast(message: string): Promise<void> {
    const toast = await this.toasts.create({ message, duration: 2500, position: 'bottom' });
    await toast.present();
  }

  /** Pregunta antes de algo que no se deshace. Resuelve true si la persona confirmó. */
  async confirm(header: string, message: string, confirmText: string): Promise<boolean> {
    const alert = await this.alerts.create({
      header,
      message,
      buttons: [
        { text: 'Cancelar', role: 'cancel' },
        { text: confirmText, role: 'destructive' },
      ],
    });
    await alert.present();
    const { role } = await alert.onDidDismiss();
    return role === 'destructive';
  }
}
