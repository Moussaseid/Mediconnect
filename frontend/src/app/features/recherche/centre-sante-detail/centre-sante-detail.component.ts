import { ChangeDetectionStrategy, Component, OnInit, OnDestroy, inject, signal } from '@angular/core';
import { CommonModule, Location } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { Subject, switchMap, catchError, of, takeUntil } from 'rxjs';
import { CentreService }  from '../../../core/services/centre.service';
import { ICentreSante }   from '../../../core/models/interfaces';

@Component({
  selector        : 'app-centre-sante-detail',
  standalone      : true,
  imports         : [CommonModule, RouterLink],
  templateUrl     : './centre-sante-detail.component.html',
  changeDetection : ChangeDetectionStrategy.OnPush,
})
export class CentreSanteDetailComponent implements OnInit, OnDestroy {

  private readonly route    = inject(ActivatedRoute);
  private readonly location = inject(Location);
  private readonly svc      = inject(CentreService);
  private readonly destroy$ = new Subject<void>();

  centre     = signal<ICentreSante | null>(null);
  chargement = signal(true);
  erreur     = signal<string | null>(null);

  ngOnInit(): void {
    this.route.paramMap.pipe(
      switchMap(p => {
        const id = Number(p.get('id'));
        if (!id) {
          this.erreur.set('Identifiant invalide.');
          this.chargement.set(false);
          return of(null);
        }
        return this.svc.getCentreSanteById(id).pipe(
          catchError(() => {
            this.erreur.set('Centre introuvable ou inaccessible.');
            return of(null);
          })
        );
      }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.chargement.set(false);
      if (res) this.centre.set(res.data);
    });
  }

  splitListe(valeur: string | undefined): string[] {
    if (!valeur?.trim()) return [];
    return valeur.split(',').map(s => s.trim()).filter(Boolean);
  }

  retour(): void { this.location.back(); }

  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }
}
