<?php

namespace Webkul\WebForm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\TypeRepository;
use Webkul\Tenant\Support\CurrentTenant;
use Webkul\WebForm\Http\Requests\WebForm;
use Webkul\WebForm\Repositories\WebFormRepository;

class WebFormController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected WebFormRepository $webFormRepository,
        protected PersonRepository $personRepository,
        protected LeadRepository $leadRepository,
        protected PipelineRepository $pipelineRepository,
        protected SourceRepository $sourceRepository,
        protected TypeRepository $typeRepository,
    ) {}

    /**
     * Remove the specified email template from storage.
     */
    public function formJS(string $formId): Response
    {
        $webForm = $this->webFormRepository->findOneByField('form_id', $formId);

        return response()->view('web_form::settings.web-forms.embed', compact('webForm'))
            ->header('Content-Type', 'text/javascript');
    }

    /**
     * Remove the specified email template from storage.
     *
     * A public, unauthenticated endpoint (a third-party site embeds this
     * with no login of its own) — there is no admin user to resolve a
     * tenant from the ordinary way, so the WebForm row itself is the only
     * place a tenant can be derived from at all. Everything past that
     * point (the existing-person lookup, and creating the Lead/Person)
     * runs inside CurrentTenant::runAs($webForm->tenant_id, ...): without
     * it, a lookup-by-email could silently merge into an unrelated
     * person belonging to a different tenant, and any created Lead/Person
     * would be stamped tenant_id NULL — invisible to the form's own
     * tenant afterward, not just visible to the wrong one.
     */
    public function formStore(int $id): JsonResponse
    {
        $webForm = $this->webFormRepository->findOrFail($id);

        return CurrentTenant::runAs($webForm->tenant_id, function () use ($webForm) {
            return $this->processFormSubmission($webForm);
        });
    }

    /**
     * The actual form-submission handling, run with the owning tenant
     * already bound by formStore() — unchanged from before except for
     * that scoping.
     */
    protected function processFormSubmission($webForm): JsonResponse
    {
        $person = $this->personRepository
            ->getModel()
            ->where('emails', 'like', '%'.request('persons.emails.0.value').'%')
            ->first();

        if ($person) {
            request()->request->add(['persons' => array_merge(request('persons'), ['id' => $person->id])]);
        }

        app(WebForm::class);

        if ($webForm->create_lead) {
            request()->request->add(['entity_type' => 'leads']);

            Event::dispatch('lead.create.before');

            $data = request('leads');

            $data['entity_type'] = 'leads';

            $data['person'] = request('persons');

            $data['status'] = 1;

            /**
             * The pipeline is configured on the web form by the admin, so the default pipeline is
             * only used when the web form does not point to one.
             */
            $pipeline = $webForm->lead_pipeline_id
                ? $this->pipelineRepository->find($webForm->lead_pipeline_id)
                : null;

            if (! $pipeline) {
                $pipeline = $this->pipelineRepository->getDefaultPipeline();
            }

            $stage = $pipeline->stages()->first();

            $data['lead_pipeline_id'] = $pipeline->id;

            $data['lead_pipeline_stage_id'] = $stage->id;

            $data['title'] = request('leads.title') ?: 'Lead From Web Form';

            $data['lead_value'] = request('leads.lead_value') ?: 0;

            if (! request('leads.lead_source_id')) {
                $source = $this->sourceRepository->findOneByField('name', 'Web Form');

                if (! $source) {
                    $source = $this->sourceRepository->first();
                }

                $data['lead_source_id'] = $source->id;
            }

            $data['lead_type_id'] = request('leads.lead_type_id') ?: $this->typeRepository->first()->id;

            $lead = $this->leadRepository->create($data);

            Event::dispatch('lead.create.after', $lead);
        } else {
            if (! $person) {
                Event::dispatch('contacts.person.create.before');

                $data = request('persons');

                request()->request->add(['entity_type' => 'persons']);

                $data['entity_type'] = 'persons';

                $person = $this->personRepository->create($data);

                Event::dispatch('contacts.person.create.after', $person);
            }
        }

        if ($webForm->submit_success_action == 'message') {
            return response()->json([
                'message' => $webForm->submit_success_content,
            ], 200);
        } else {
            return response()->json([
                'redirect' => $webForm->submit_success_content,
            ], 301);
        }
    }

    /**
     * Remove the specified email template from storage.
     */
    public function preview(string $id): View
    {
        $webForm = $this->webFormRepository->findOneByField('form_id', $id);

        if (is_null($webForm)) {
            abort(404);
        }

        return view('web_form::settings.web-forms.preview', compact('webForm'));
    }

    /**
     * Preview the web form from datagrid.
     *
     * The null check has to come before request()->merge() reads
     * $webForm->form_id — reachable even before this route carried tenant
     * scoping (any nonexistent id), but scoping (see routes.php) makes it
     * reachable for any OTHER tenant's real id too, since findOneByField
     * now genuinely returns null for one instead of finding it anyway.
     */
    public function view(int $id): View
    {
        $webForm = $this->webFormRepository->findOneByField('id', $id);

        if (is_null($webForm)) {
            abort(404);
        }

        request()->merge(['id' => $webForm->form_id]);

        return view('web_form::settings.web-forms.preview', compact('webForm'));
    }
}
