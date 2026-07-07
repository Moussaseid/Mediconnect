import { Component, OnInit, OnDestroy, inject, signal } from '@angular/core';
import { ChangeDetectionStrategy } from '@angular/core';
import { CommonModule, Location } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { Subject, switchMap, catchError, of, takeUntil } from 'rxjs';
import { CentreService }         from '../../../core/services/centre.service';
import { ICentreAnalyse }        from '../../../core/models/interfaces';

@Component({
  selector   : 'app-centre-analyse-detail',
  standalone : true,
  imports         : [CommonModule, RouterLink],
  templateUrl     : './centre-analyse-detail.component.html',
  changeDetection : ChangeDetectionStrategy.OnPush,
})
export class CentreAnalyseDetailComponent implements OnInit, OnDestroy {

  private readonly route    = inject(ActivatedRoute);
  private readonly location = inject(Location);
  private readonly svc      = inject(CentreService);
  private readonly destroy$ = new Subject<void>();

  centre     = signal<ICentreAnalyse | null>(null);
  chargement = signal(true);
  erreur     = signal<string | null>(null);

  ngOnInit(): void {
    this.route.paramMap.pipe(
      switchMap(p => {
        const id = Number(p.get('id'));
        if (!id) { this.erreur.set('Identifiant invalide.'); this.chargement.set(false); return of(null); }
        return this.svc.getCentreAnalyseById(id).pipe(
          catchError(() => { this.erreur.set('Centre introuvable.'); return of(null); })
        );
      }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.chargement.set(false);
      if (res) this.centre.set(res.data);
    });
  }

  retour(): void { this.location.back(); }

  getCategories(analyses: ICentreAnalyse['analyses']): string[] {
    return [...new Set((analyses ?? []).map(a => a.analyse?.categorie ?? 'Autre'))];
  }

  getParCategorie(analyses: ICentreAnalyse['analyses'], cat: string) {
    return (analyses ?? []).filter(a => (a.analyse?.categorie ?? 'Autre') === cat);
  }

  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }
}
