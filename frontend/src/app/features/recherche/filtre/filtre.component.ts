import { Component, Input, Output, EventEmitter, OnInit, inject } from '@angular/core';
import { CommonModule }     from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup } from '@angular/forms';
import { ISpecialite }                                  from '../../../core/models/interfaces';
import { AdresseAutocompleteComponent }                 from '../adresse-autocomplete/adresse-autocomplete.component';
import { IAdresseSuggestion }                            from '../../../core/services/adresse.service';
import { IRechercheMedecinParamsEtendu }                 from '../../../core/services/medecin.service';

@Component({
  selector   : 'app-filtre',
  standalone : true,
  imports    : [CommonModule, ReactiveFormsModule, AdresseAutocompleteComponent],
  templateUrl: './filtre.component.html',
})
export class FiltreComponent implements OnInit {
  @Input()  valeurs: IRechercheMedecinParamsEtendu = {};
  @Input()  specialites: ISpecialite[]              = [];
  @Input()  valeurAdresse?: string;
  @Output() filtresChange = new EventEmitter<IRechercheMedecinParamsEtendu>();

  private fb = inject(FormBuilder);

  readonly rayonMin = 5;
  readonly rayonMax = 100;
  form!: FormGroup;

  ngOnInit(): void {
    this.form = this.fb.group({
      specialiteId: [this.valeurs.specialiteId ?? null],
      rayon        : [this.valeurs.rayon        ?? 10],
      ville        : [this.valeurs.ville         ?? ''],
    });

    this.form.valueChanges.subscribe(v =>
      this.filtresChange.emit({
        ...this.valeurs,
        specialiteId: v.specialiteId ? +v.specialiteId : undefined,
        rayon        : +v.rayon,
        ville        : v.ville?.trim() || undefined,
      })
    );
  }

  onAdresseSelectionnee(s: IAdresseSuggestion): void {
    this.filtresChange.emit({
      ...this.valeurs,
      specialiteId: this.form.value.specialiteId ? +this.form.value.specialiteId : undefined,
      rayon        : +this.form.value.rayon,
      ville        : this.form.value.ville?.trim() || undefined,
      lat: s.lat,
      lng: s.lng,
    });
  }

  onAdresseEffacee(): void {
    this.filtresChange.emit({
      ...this.valeurs,
      specialiteId: this.form.value.specialiteId ? +this.form.value.specialiteId : undefined,
      rayon        : +this.form.value.rayon,
      ville        : this.form.value.ville?.trim() || undefined,
      lat: undefined,
      lng: undefined,
    });
  }
}
