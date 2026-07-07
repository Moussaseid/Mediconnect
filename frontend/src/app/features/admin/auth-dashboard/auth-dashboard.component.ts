// src/app/features/admin/auth-dashboard/auth-dashboard.component.ts
import { Component, signal, OnInit, inject } from '@angular/core';
import { CommonModule }    from '@angular/common';
import { RouterLink }      from '@angular/router';

import { NgxChartsModule, Color, ScaleType } from '@swimlane/ngx-charts';
import { MatCardModule }              from '@angular/material/card';
import { MatButtonModule }            from '@angular/material/button';
import { MatIconModule }              from '@angular/material/icon';
import { MatProgressSpinnerModule }   from '@angular/material/progress-spinner';
import { MatTableModule }             from '@angular/material/table';
import { MatTooltipModule }           from '@angular/material/tooltip';

import { AdminService, IAuthStats }   from '../../../core/services/admin.service';
import { AuthService }                from '../../../core/services/auth.service';
import jsPDF                          from 'jspdf';

interface ISerie      { name: string; value: number; }
interface IChartSerie { name: string; series: ISerie[]; }

@Component({
  selector   : 'app-auth-dashboard',
  standalone : true,
  imports    : [
    CommonModule, RouterLink,
    NgxChartsModule,
    MatCardModule, MatButtonModule, MatIconModule,
    MatProgressSpinnerModule, MatTableModule, MatTooltipModule,
  ],
  templateUrl: './auth-dashboard.component.html',
})
export class AuthDashboardComponent implements OnInit {

  private admin = inject(AdminService);
  auth          = inject(AuthService);

  stats      = signal<IAuthStats | null>(null);
  chargement = signal(true);
  erreur     = signal<string | null>(null);
  chartData  = signal<IChartSerie[]>([]);

  readonly SEUIL_ALERTE = 3;

  readonly colorScheme: Color = {
    name     : 'mc',
    selectable: true,
    group    : ScaleType.Ordinal,
    domain   : ['#3b82f6', '#ef4444'],
  };

  colonnesIp = ['ip', 'tentatives', 'risque'];

  ngOnInit(): void {
    this.admin.getAuthStats().subscribe({
      next : res => {
        this.stats.set(res.data);
        this.chartData.set(this.buildChartData(res.data));
        this.chargement.set(false);
      },
      error: err => {
        this.erreur.set(err.error?.error ?? 'Erreur de chargement des statistiques');
        this.chargement.set(false);
      },
    });
  }

  get alertes() {
    return (this.stats()?.topIpsEchecs ?? [])
      .filter(ip => ip.tentatives >= this.SEUIL_ALERTE);
  }

  get niveauRisque(): 'faible' | 'moyen' | 'eleve' {
    const nb = this.alertes.length;
    if (nb === 0) return 'faible';
    if (nb <= 2)  return 'moyen';
    return 'eleve';
  }

  risqueClass(tentatives: number): string {
    if (tentatives >= 10) return 'text-danger fw-bold';
    if (tentatives >= 5)  return 'text-warning fw-semibold';
    return 'text-muted';
  }

  exportPdf(): void {
    const s = this.stats();
    if (!s) return;

    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const now = new Date();
    const w   = doc.internal.pageSize.getWidth();

    // En-tête colorée
    doc.setFillColor(37, 99, 235);
    doc.rect(0, 0, w, 28, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(18);
    doc.setFont('helvetica', 'bold');
    doc.text('MediConnect — Rapport Sécurité', w / 2, 12, { align: 'center' });
    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    doc.text(
      `Généré le ${now.toLocaleDateString('fr-FR')} à ${now.toLocaleTimeString('fr-FR')}`,
      w / 2, 21, { align: 'center' }
    );

    doc.setTextColor(0, 0, 0);
    let y = 38;

    // KPIs
    doc.setFontSize(13); doc.setFont('helvetica', 'bold');
    doc.text('Résumé — 30 derniers jours', 14, y); y += 8;

    const total = s.totalSucces + s.totalEchecs;
    const kpis  = [
      ['Connexions réussies',  `${s.totalSucces}`],
      ['Tentatives échouées',  `${s.totalEchecs}`],
      ['Taux de succès',       total > 0 ? `${Math.round(s.totalSucces / total * 100)} %` : 'N/A'],
      ['Source données',       s.source === 'mongodb' ? 'MongoDB' : 'Fichier log'],
    ];

    doc.setFontSize(10);
    kpis.forEach(([label, val]) => {
      doc.setFont('helvetica', 'bold');   doc.text(`${label} :`, 14, y);
      doc.setFont('helvetica', 'normal'); doc.text(val, 80, y);
      y += 7;
    });
    y += 4;

    // Tableau activité / jour (14 derniers jours)
    doc.setFontSize(13); doc.setFont('helvetica', 'bold');
    doc.text('Activité connexions / jour (14 derniers jours)', 14, y); y += 6;

    this.drawTable(doc, y,
      ['Date', 'Connexions', 'Échecs'],
      [50, 40, 40],
      s.parJour.slice(-14).map(r => [r.date, `${r.connexions}`, `${r.echecs}`]),
      [219, 234, 254]
    );
    y += 7 + Math.min(14, s.parJour.length) * 6 + 8;

    // Tableau IP suspectes
    if (s.topIpsEchecs.length > 0) {
      if (y > 220) { doc.addPage(); y = 20; }
      doc.setFontSize(13); doc.setFont('helvetica', 'bold');
      doc.text('Adresses IP — tentatives échouées (Top 10)', 14, y); y += 6;

      this.drawTable(doc, y,
        ['Adresse IP', 'Tentatives', 'Niveau'],
        [70, 35, 40],
        s.topIpsEchecs.map(ip => [
          ip.ip,
          `${ip.tentatives}`,
          ip.tentatives >= 10 ? 'CRITIQUE' : ip.tentatives >= 5 ? 'ÉLEVÉ' : 'MODÉRÉ',
        ]),
        [254, 226, 226]
      );
    }

    // Pied de page
    const total_pages = (doc.internal as any).getNumberOfPages();
    for (let i = 1; i <= total_pages; i++) {
      doc.setPage(i);
      doc.setFontSize(8); doc.setTextColor(150);
      doc.text(`Page ${i} / ${total_pages} — MediConnect © ${now.getFullYear()}`,
               w / 2, 290, { align: 'center' });
    }

    doc.save(`mediconnect-securite-${now.toISOString().slice(0, 10)}.pdf`);
  }

  private drawTable(
    doc    : jsPDF,
    y      : number,
    headers: string[],
    colW   : number[],
    rows   : string[][],
    hColor : [number, number, number]
  ): void {
    doc.setFillColor(...hColor);
    const totalW = colW.reduce((a, b) => a + b, 0);
    doc.rect(14, y, totalW, 7, 'F');
    doc.setFontSize(9); doc.setFont('helvetica', 'bold'); doc.setTextColor(0, 0, 0);
    let cx = 14;
    headers.forEach((h, i) => { doc.text(h, cx + 2, y + 5); cx += colW[i]; });
    y += 7;

    doc.setFont('helvetica', 'normal');
    rows.forEach((row, idx) => {
      if (idx % 2 === 0) { doc.setFillColor(248, 250, 252); doc.rect(14, y, totalW, 6, 'F'); }
      cx = 14;
      row.forEach((v, i) => { doc.text(v, cx + 2, y + 4.5); cx += colW[i]; });
      y += 6;
    });
  }

  private buildChartData(s: IAuthStats): IChartSerie[] {
    return [
      { name: 'Connexions réussies', series: s.parJour.map(j => ({ name: j.date.slice(5), value: j.connexions })) },
      { name: 'Tentatives échouées', series: s.parJour.map(j => ({ name: j.date.slice(5), value: j.echecs })) },
    ];
  }
}
