import {
  Component, Input, OnChanges, OnDestroy, AfterViewInit,
  SimpleChanges, ElementRef, ViewChild,
} from '@angular/core';
import * as L from 'leaflet';
import { IMedecin } from '../../../core/models/interfaces';

// Correction icône Leaflet avec Webpack/Vite (chemins cassés par défaut)
const iconDefault = L.icon({
  iconUrl     : 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  shadowUrl   : 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
  iconSize    : [25, 41],
  iconAnchor  : [12, 41],
  popupAnchor : [1, -34],
  shadowSize  : [41, 41],
});

@Component({
  selector   : 'app-carte',
  standalone : true,
  template   : `<div #mapEl style="height:400px;border-radius:.5rem"></div>`,
})
export class CarteComponent implements AfterViewInit, OnChanges, OnDestroy {

  @Input({ required: true }) medecins: IMedecin[] = [];
  @Input() positionUtilisateur: [number, number] | null = null;
  @Input() rayonKm: number | null = null;
  @Input() adresseUtilisateur?: string;

  @ViewChild('mapEl', { static: true }) mapEl!: ElementRef<HTMLDivElement>;

  private map!: L.Map;
  private markersLayer = L.layerGroup();

  ngAfterViewInit(): void {
    this.map = L.map(this.mapEl.nativeElement).setView([46.8, 2.3], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
      maxZoom    : 18,
    }).addTo(this.map);

    this.markersLayer.addTo(this.map);
    this.rafraichirMarqueurs();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (!this.map) return;
    if (changes['medecins'] || changes['positionUtilisateur'] || changes['rayonKm'] || changes['adresseUtilisateur']) {
      this.rafraichirMarqueurs();
    }
  }

  private rafraichirMarqueurs(): void {
    this.markersLayer.clearLayers();
    const bounds: L.LatLngTuple[] = [];

    // Marqueur position de recherche (adresse choisie ou géolocalisation exacte)
    if (this.positionUtilisateur) {
      const [lat, lng] = this.positionUtilisateur;

      const positionIcon = L.divIcon({
        html      : `<div class="mc-user-marker"><div class="mc-user-marker__pulse"></div><div class="mc-user-marker__dot"></div></div>`,
        className : '',
        iconSize  : [16, 16],
        iconAnchor: [8, 8],
        popupAnchor: [0, -14],
      });

      const popupContenu = this.adresseUtilisateur
        ? `<strong>Votre position</strong><br><small style="color:#64748b">${this.adresseUtilisateur}</small>`
        : '<strong>Votre position</strong>';

      L.marker([lat, lng], { icon: positionIcon })
        .bindPopup(popupContenu)
        .addTo(this.markersLayer);

      bounds.push([lat, lng]);

      // Cercle représentant le rayon de recherche — la carte s'ajuste à son étendue
      if (this.rayonKm != null && this.rayonKm > 0) {
        const cercle = L.circle([lat, lng], {
          radius     : this.rayonKm * 1000,
          color      : '#0d6efd',
          weight     : 1,
          fillOpacity: 0.06,
        }).addTo(this.markersLayer);
        const cercleBounds = cercle.getBounds();
        bounds.push(
          [cercleBounds.getSouth(), cercleBounds.getWest()],
          [cercleBounds.getNorth(), cercleBounds.getEast()],
        );
      }
    }

    // Marqueurs médecins
    for (const m of this.medecins) {
      if (m.latitude == null || m.longitude == null) continue;
      const lat = m.latitude;
      const lng = m.longitude;

      const distanceHtml = m.distance != null
        ? `<br><small class="text-muted">${m.distance} km</small>`
        : '';

      L.marker([lat, lng], { icon: iconDefault })
        .bindPopup(`
          <strong>Dr ${m.prenom} ${m.nom}</strong><br>
          ${m.specialisationLibelle ?? ''}<br>
          <small>${m.adresseCabinet ?? ''}</small>
          ${distanceHtml}
        `)
        .addTo(this.markersLayer);

      bounds.push([lat, lng]);
    }

    if (bounds.length > 0) {
      this.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
    }
  }

  ngOnDestroy(): void {
    this.map?.remove();
  }
}