<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Session;
use App\Apartment;
use App\Category;
use App\Service;
use App\View;
use Carbon\Carbon;
use DB;


class ApartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    //Ritorna la view con la lista degli appartamenti dello user loggato
    public function index()
    {
        $apartments = Apartment::where('user_id', Auth::id())->get();
        return view('admin.index', compact('apartments'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */

    //Ritorna la view per la creazione dell'appartamento tramite form//
    public function create()
    {
        $categories = Category::all();
        $services = Service::all();
        return view('admin.create', compact('categories', 'services'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

     //Salvataggio dei dati inseriti//
    public function store(Request $request)
    {
        $data = $request->all();
        //Validazione campi della tabella Apartments//
        $request->validate([
            'title' =>  'required|min:10|max:150',
            'rooms' =>  'required|numeric|min:0',
            'beds' =>  'required|numeric|min:1|gt:0',
            'bathrooms' =>  'required|numeric|min:1|gt:0',
            'square_meters' =>  'required|numeric|gt:0',
            'latitude' =>  'min:-90|max:90',
            'longitude' =>  'min:-180|max:180',
            'image' =>  'required',
            'description' =>  'required|min:60',
        ]);
        //Assegnazione user_id per appartamento creato//
        $data['user_id'] = Auth::id();

        $apartment = new Apartment();

        //Salvataggio immagine relativa all'appartamento//
        if(!empty($data['image'])){
            $data['image'] = Storage::disk('public')->put('images', $data['image']);
        }
        //Disponibilità appartamento //
        if(array_key_exists('available', $data)){
            $data['available'] = 1;
        }

        $apartment->fill($data);

        //Salvataggio nella tabella ponte tra 'apartments' e 'services' dei servizi selezionati
        // dd($request->all());
        $apartment->save();

        if(array_key_exists("services",$data)){
            $apartment->services()->attach($data['services']);
        }
        // Ritorno alla views degli appartamenti con relativo messaggio//
        return redirect()->route('apartments.index')->with('status', 'Appartamento "'.$apartment->title.'" aggiunto correttamente.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    //Ritorna la view con i dettagli dell'appartamento selezionato
    public function show($id)
    {
        $apartment = Apartment::where('id',$id)->first();
        // dd(Carbon::parse($apartment->sponsors()->orderBy('pivot_end_date', 'desc')->first()->pivot->end_date));
        // controllo se l'appartamento è disponibile
        if ($apartment->available){
            if ($apartment->user_id != Auth::id()){
                //controllo se è dell'utente loggato (in questo caso aggiungo una visita alla pagina)
                if (!View::where([['apartment_id', $id], ['session_id', Session::getId()]])->exists()){
                $view = new View;
                $view->created_at = Carbon::now()->timezone('Europe/Rome')->format('d-M-Y h:00:00');
                $view->apartment_id = $id;
                $view->session_id = Session::getId();

                $view->save();
                }
                //controllo se l'appartamento è sponsorizzato
            } else if ($apartment->sponsors()->orderBy('pivot_end_date', 'desc')->first() && Carbon::parse($apartment->sponsors()->orderBy('pivot_end_date', 'desc')->first()->pivot->end_date)->gt(Carbon::now()->timezone('Europe/Rome'))){
                $sponsored = 'Sponsorizzato fino al '.Carbon::parse($apartment->sponsors()->orderBy('pivot_end_date', 'desc')->first()->pivot->end_date)->format('d-m').' alle '.Carbon::parse($apartment->sponsors()->orderBy('pivot_end_date', 'desc')->first()->pivot->end_date)->format('H:i');
                return view('admin.show',compact('apartment', 'sponsored'));
            }

            // altrimenti mostro solo la view
            return view('admin.show',compact('apartment'));

        } else { // se non è disponibile lo mostro solo se l'appartamento appartiene all'utente loggato
            if ($apartment->user_id == Auth::id()){
                if ($apartment->sponsors()->orderBy('pivot_end_date', 'desc')->first() && Carbon::parse($apartment->sponsors()->orderBy('pivot_end_date', 'desc')->first()->pivot->end_date)->gt(Carbon::now()->timezone('Europe/Rome'))){
                    $sponsored = 'Sponsorizzato fino al '.Carbon::parse($apartment->sponsors()->orderBy('pivot_end_date', 'desc')->first()->pivot->end_date)->format('d-m').' alle '.Carbon::parse($apartment->sponsors()->orderBy('pivot_end_date', 'desc')->first()->pivot->end_date)->format('H:i');
                    return view('admin.show',compact('apartment', 'sponsored'));
                } else {
                    return view('admin.show',compact('apartment'));
                }

            } else {
                abort(404);
            }

        }



    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

      //Ritorna la view per la modifica dell'appartamento tramite form//
    public function edit(Apartment $apartment)
    {
        $categories = Category::all();
        $services = Service::all();
        return view('admin.edit', compact('apartment', 'categories', 'services'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    //Permette di modificare i campi di un relativo appartamento//
    public function update(Request $request, Apartment $apartment)
    {
        $data = $request->all();
        $request->validate([
            'title' =>  'required|min:10|max:150',
            'rooms' =>  'required|numeric|min:0',
            'beds' =>  'required|numeric|min:1|gt:0',
            'bathrooms' =>  'required|numeric|min:1|gt:0',
            'square_meters' =>  'required|numeric|gt:0',
            'latitude' =>  'min:-90|max:90',
            'longitude' =>  'min:-180|max:180',
            'description' =>  'required|min:60',
        ]);

        //Caricamento/modifica immagine:
        if (!empty($data['image'])) {
        //per eliminare l'img non più in uso:
        if (!empty($apartment->image)) {
            Storage::disk('public')->delete($apartment->image);
        }
        $data['image'] = Storage::disk('public')->put('images', $data['image']);
}

        //Modifica del campo available //
        if(array_key_exists('available', $data)){
            $data['available'] = 1;//Disponibile
        } else {
            $data['available'] = 0;//Non Disponibile
        }
        // Ritorno alla views degli appartamneti con relativo messaggio//
        $apartment->update($data);
        if(array_key_exists("services",$data)){
            $apartment->services()->sync($data['services']);
        } else {
            $apartment->services()->detach();
        }
        return redirect()->route('apartments.index')->with('status', 'Appartamento "'.$apartment->title.'" modificato correttamente.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    //Cancellazione di un singolo appartamento
    public function destroy(Apartment $apartment)
    {
        $apartment->delete();
        // Ritorno alla views degli appartamneti con relativo messaggio//
        return redirect()->route('apartments.index')->with('status', 'Appartamento "'.$apartment->title.'" eliminato correttamente.');
    }
}
