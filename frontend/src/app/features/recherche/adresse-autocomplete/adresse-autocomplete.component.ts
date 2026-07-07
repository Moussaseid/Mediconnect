import { Component, Input, Output, EventEmitter, OnInit, OnDestroy, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, debounceTime, distinctUntilChanged, switchMap, takeUntil } from 'rxjs';

import { AdresseService, IAdresseSuggestion } from '../../../core/services/adresse.service';

/**
 * Champ de saisie d'adresse avec autocomplétion (API Adresse / BAN).
 * Émet la suggestion choisie (libellé + coordonnées) — ou `null` si l'utilisateur vide le champ.
 */
@Component({
  selector   : 'app-adresse-autocomplete',
  standalone : true,
  imports    : [CommonModule],
  templateUrl: './adresse-autocomplete.component.html',
})
export class AdresseAutocompleteComponent implements OnInit, OnDestroy {

  @Input()  placeholder = 'Saisir une adresse…';
  @Input()  set valeur(v: string | undefined) { this.requete.set(v ?? ''); }
  @Output() adresseSelectionnee = new EventEmitter<IAdresseSuggestion>();
  @Output() adresseEffacee      = new EventEmitter<void>();

  requete     = signal('');
  suggestions = signal<IAdresseSuggestion[]>([]);
  ouvert      = signal(false);
  chargement  = signal(false);

  private readonly adresseSvc = inject(AdresseService);
  private readonly destroy$ = new Subject<void>();
  private readonly saisie$  = new Subject<string>();

  ngOnInit(): void {
    this.saisie$.pipe(
      debounceTime(300),
      distinctUntilChanged(),
      switchMap(q => {
        this.chargement.set(true);
        return this.adresseSvc.rechercher(q);
      }),
      takeUntil(this.destroy$)
    ).subscribe(suggestions => {
      this.chargement.set(false);
      this.suggestions.set(suggestions);
      this.ouvert.set(suggestions.length > 0);
    });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  onSaisie(valeur: string): void {
    this.requete.set(valeur);
    if (valeur.trim() === '') {
      this.suggestions.set([]);
      this.ouvert.set(false);
      this.adresseEffacee.emit();
      return;
    }
    this.saisie$.next(valeur);
  }

  choisir(s: IAdresseSuggestion): void {
    this.requete.set(s.label);
    this.suggestions.set([]);
    this.ouvert.set(false);
    this.adresseSelectionnee.emit(s);
  }

  fermer(): void {
    this.ouvert.set(false);
  }
}