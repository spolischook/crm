<?php

namespace OroCRM\Bundle\ChannelBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\IntegrationBundle\Form\Handler\ChannelHandler as IntegrationChannelHandler;

use OroCRM\Bundle\ChannelBundle\Entity\Channel;
use OroCRM\Bundle\ChannelBundle\Event\ChannelChangeStatusEvent;
use Symfony\Component\Form\FormInterface;

class ChannelController extends Controller
{
    /**
     * @Route("/", name="orocrm_channel_index")
     * @Acl(
     *      id="orocrm_channel_view",
     *      type="entity",
     *      permission="VIEW",
     *      class="OroCRMChannelBundle:Channel"
     * )
     * @Template()
     */
    public function indexAction()
    {
        return [];
    }

    /**
     * @Route("/create", name="orocrm_channel_create")
     * @Acl(
     *      id="orocrm_channel_create",
     *      type="entity",
     *      permission="CREATE",
     *      class="OroCRMChannelBundle:Channel"
     * )
     * @Template("OroCRMChannelBundle:Channel:update.html.twig")
     */
    public function createAction()
    {
        return $this->update(new Channel());
    }

    /**
     * @Route("/update/{id}", requirements={"id"="\d+"}, name="orocrm_channel_update")
     * @Acl(
     *      id="orocrm_channel_update",
     *      type="entity",
     *      permission="EDIT",
     *      class="OroCRMChannelBundle:Channel"
     * )
     * @Template()
     */
    public function updateAction(Channel $channel)
    {
        return $this->update($channel);
    }

    /**
     * @param Channel $channel
     *
     * @return array
     */
    protected function update(Channel $channel)
    {
        if ($this->get('orocrm_channel.channel_form.handler')->process($channel)) {
            $this->get('session')->getFlashBag()->add(
                'success',
                $this->get('translator')->trans('orocrm.channel.controller.message.saved')
            );

            return $this->get('oro_ui.router')->redirectAfterSave(
                ['route' => 'orocrm_channel_update', 'parameters' => ['id' => $channel->getId()]],
                ['route' => 'orocrm_channel_index'],
                $channel
            );
        }

        $form = $this->getForm();

        return [
            'entity' => $channel,
            'form'   => $form->createView(),
        ];
    }

    /**
     * @Route("/status/change/{id}", requirements={"id"="\d+"}, name="orocrm_channel_change_status")
     * @AclAncestor("orocrm_channel_update")
     */
    public function changeStatusAction(Channel $channel)
    {
        if ($channel->getStatus() == Channel::STATUS_ACTIVE) {
            $message = 'orocrm.channel.controller.message.status.deactivated';
            $channel->setStatus($channel::STATUS_INACTIVE);
        } else {
            $message = 'orocrm.channel.controller.message.status.activated';
            $channel->setStatus($channel::STATUS_ACTIVE);
        }

        $this->getDoctrine()
            ->getManager()
            ->flush();

        $event = new ChannelChangeStatusEvent($channel);
        $this->get('event_dispatcher')->dispatch(ChannelChangeStatusEvent::EVENT_NAME, $event);
        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans($message));

        return $this->redirect($this->generateUrl('orocrm_channel_update', ['id' => $channel->getId()]));
    }

    /**
     * Returns form instance
     *
     * @return FormInterface
     */
    protected function getForm()
    {
        $isUpdateOnly = $this->get('request')->get(IntegrationChannelHandler::UPDATE_MARKER, false);

        $form = $this->get('orocrm_channel.form.channel');
        // take different form due to JS validation should be shown even in case when it was not validated on backend
        if ($isUpdateOnly) {
            $form = $this->get('form.factory')
                ->createNamed('orocrm_channel_form', 'orocrm_channel_form', $form->getData());
        }

        return $form;
    }
}
