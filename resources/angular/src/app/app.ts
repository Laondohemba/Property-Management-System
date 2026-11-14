import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';

import { ReleaseReloadService } from './core/release/release-reload';
import { ThemeService } from './core/theme/theme.service';

@Component({
  selector: 'pms-root',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterOutlet],
  template: '<router-outlet />',
})
export class App {
  constructor() {
    // Apply the light/dark theme before the first screen renders.
    inject(ThemeService);
    // Pick up a newer release at the next navigation (spec §4.4).
    inject(ReleaseReloadService);
  }
}
