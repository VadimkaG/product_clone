<?php
namespace Drupal\product_clone\Form;
class ProductCloneForm extends \Drupal\Core\Form\ConfirmFormBase {

	/**
	 * Товар, который нужно отклонировать
	 * \Drupal\commerce_product\Entity\Product
	 */
	private $product;

	/**
	 * Проверка доступа к форме
	 * @return Drupal\Core\Access\AccessResult
	 */
	public function hasAccess(string $product) {
		$account = \Drupal::currentUser();
		$product = \Drupal\commerce_product\Entity\Product::load($product);
		return $product
			->access('update', $account, TRUE)
			->andIf($product->status->access('edit', $account, TRUE));
	}
	/**
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, \Drupal\commerce_product\Entity\Product $product = NULL) {
		$this->product = $product;
		return parent::buildForm($form, $form_state);
	}
	/**
	 * {@inheritdoc}
	 */
	public function getFormId() {
		return "product_clone_confirm";
	}
	/**
	 * {@inheritdoc}
	 */
	public function getCancelUrl() {
		if (isset($_GET["destination"]))
			return $_GET["destination"];
		else
			return \Drupal\core\Url::fromRoute("commerce_product.configuration");
	}
	/**
	 * {@inheritdoc}
	 */
	public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
		$action = \Drupal::entityTypeManager()
			->getStorage('action')
			->load('clone_product_action');
		if ($action) {
			$action->execute([ $this->product ]);
			$title = $this->product->title->getValue();
			if (isset($title[0]["value"]))
				$arr = [ "%id" => $title[0]["value"] ];
			else
				$arr = [ "%id" => $this->product->id() ];
			\Drupal::messenger()->addMessage(t('Товар "%id" успешно отклонирован.', $arr));
		}
	}
	/**
	 * {@inheritdoc}
	 */
	public function getQuestion() {
		$title = $this->product->title->getValue();
		if (isset($title[0]["value"]))
			$arr = [ "%id" => $title[0]["value"] ];
		else
			$arr = [ "%id" => $this->product->id() ];
		return t('Вы уверены, что хотите клонировать %id?', ['%id' => $title[0]["value"]]);
	}
}