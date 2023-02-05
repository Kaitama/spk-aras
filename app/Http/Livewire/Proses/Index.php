<?php

namespace App\Http\Livewire\Proses;

use App\Models\Alternatif;
use App\Models\Kriteria;
use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;

class Index extends Component
{
	public function render()
	{
		$alternatifs = $this->proses();
		return view('livewire.proses.index', compact('alternatifs'));
	}

	public function print()
	{
		// abaikan garis error di bawah 'Pdf' jika ada.
		$pdf = Pdf::loadView('laporan.cetak', ['data' => $this->proses()])->output();
		return response()->streamDownload(fn () => print($pdf), 'Laporan.pdf');
	}

	// proses metode ARAS
	public function proses()
	{
		$alternatifs = Alternatif::orderBy('kode')->get();
		$kriterias = Kriteria::orderBy('kode')->get()->toArray();

		// pembentukan matriks awal
		$matriks = [];
		$X = [];
		foreach ($alternatifs as $ka => $alt) {
			foreach ($alt->kriteria as $kb => $krit) {
				$matriks[$kb][$ka] = $krit->pivot->nilai;
				$X[$ka][$kb] = $krit->pivot->nilai;
			}
		}

		// penentuan nilai A0
		$A0 = [];
		foreach ($matriks as $k => $matrik) {
			// jika benefit
			if ($kriterias[$k]['type'] == true) {
				$A0[] = max($matrik);
			}
			// jika cost 
			else {
				$A0[] = min($matrik);
			}
		}

		// pembentukan matriks keputusan
		array_unshift($X, $A0);
		$matriks_x = [];
		foreach ($X as $key => $item) {
			foreach ($item as $subkey => $subitem) {
				$matriks_x[$subkey][$key] = $subitem;
			}
		}

		// normalisasi matriks keputusan
		$R = [];
		foreach ($matriks_x as $key => $value) {
			$divisor = array_sum($value);
			$benefit = $kriterias[$key]['type'];
			foreach ($value as $subkey => $subvalue) {
				if ($benefit) {
					$R[$key][$subkey] = $subvalue / $divisor;
				} else {
					$R[$key][$subkey] = (1 / $subvalue) / $divisor;
				}
			}
		}

		// menentukan bobot matriks
		$D = [];
		foreach ($R as $key => $value) {
			$bobot = $kriterias[$key]['bobot'];
			foreach ($value as $subkey => $subvalue) {
				$D[$key][$subkey] = $subvalue * $bobot;
			}
		}

		// menentukan nilai fungsi optimalisasi
		$matriks_opt = [];
		foreach ($D as $key => $value) {
			foreach ($value as $subkey => $subvalue) {
				$matriks_opt[$subkey][$key] = $subvalue;
			}
		}
		$sum_s = [];
		foreach ($matriks_opt as $key => $value) {
			$sum_s[] = array_sum($value);
		}
		$S0 = array_sum($sum_s);

		// menentukan peringkat/ranking
		$K = [];
		foreach ($sum_s as $key => $value) {
			$K[] = $value / $S0;
		}

		// masukkan hasil perhitungan ke dalam data alternatif
		foreach ($alternatifs as $key => $alternatif) {
			$alternatif->nilai = round($K[$key + 1], 4);
		}

		return $alternatifs;
	}
}