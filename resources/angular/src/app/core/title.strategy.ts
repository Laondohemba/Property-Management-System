import { Injectable, inject } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { RouterStateSnapshot, TitleStrategy } from '@angular/router';

/** "Dashboard · PMS" style document titles. */
@Injectable()
export class PmsTitleStrategy extends TitleStrategy {
  private readonly title = inject(Title);

  override updateTitle(snapshot: RouterStateSnapshot): void {
    const pageTitle = this.buildTitle(snapshot);

    this.title.setTitle(pageTitle ? `${pageTitle} · PMS` : 'PMS');
  }
}
