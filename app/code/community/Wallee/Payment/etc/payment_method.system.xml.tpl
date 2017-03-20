<config>
	<sections>
		<payment>
			<groups>
				<wallee_payment_{id}>
					<label>Wallee Payment - {name}</label>
					<frontend_type>text</frontend_type>
					<sort_order>1</sort_order>
					<show_in_default>1</show_in_default>
					<show_in_website>1</show_in_website>
					<show_in_store>1</show_in_store>
					<fields>
						<active translate="label">
							<label>Enabled</label>
							<frontend_type>label</frontend_type>
							<frontend_model>wallee_payment/adminhtml_system_config_form_field_state</frontend_model>
							<sort_order>10</sort_order>
							<show_in_default>0</show_in_default>
							<show_in_website>1</show_in_website>
							<show_in_store>1</show_in_store>
						</active>
						<title translate="label">
							<label>Title</label>
							<frontend_type>label</frontend_type>
							<frontend_model>wallee_payment/adminhtml_system_config_form_field_label</frontend_model>
							<sort_order>20</sort_order>
							<show_in_default>0</show_in_default>
							<show_in_website>1</show_in_website>
							<show_in_store>1</show_in_store>
						</title>
						<description translate="label">
							<label>Description</label>
							<frontend_type>label</frontend_type>
							<frontend_model>wallee_payment/adminhtml_system_config_form_field_label</frontend_model>
							<sort_order>30</sort_order>
							<show_in_default>0</show_in_default>
							<show_in_website>1</show_in_website>
							<show_in_store>1</show_in_store>
						</description>
						<display_heading translate="label">
                            <label>Display</label>
                            <frontend_model>adminhtml/system_config_form_field_heading</frontend_model>
                            <sort_order>35</sort_order>
                            <show_in_default>0</show_in_default>
                            <show_in_website>1</show_in_website>
                            <show_in_store>1</show_in_store>
                        </display_heading>
						<show_description translate="label comment">
							<label>Show Description</label>
							<frontend_type>select</frontend_type>
							<sort_order>40</sort_order>
							<comment>Show the payment method's description on the checkout page.</comment>
							<source_model>adminhtml/system_config_source_yesno</source_model>
							<show_in_default>0</show_in_default>
							<show_in_website>1</show_in_website>
							<show_in_store>1</show_in_store>
						</show_description>
						<show_image translate="label comment">
							<label>Show Image</label>
							<frontend_type>select</frontend_type>
							<sort_order>50</sort_order>
							<comment>Show the payment method's image on the checkout page.</comment>
							<source_model>adminhtml/system_config_source_yesno</source_model>
							<show_in_default>0</show_in_default>
							<show_in_website>1</show_in_website>
							<show_in_store>1</show_in_store>
						</show_image>
						<sort_order translate="label">
							<label>Sort Order</label>
							<frontend_type>text</frontend_type>
							<sort_order>100</sort_order>
							<show_in_default>0</show_in_default>
							<show_in_website>1</show_in_website>
							<show_in_store>1</show_in_store>
						</sort_order>
					</fields>
				</wallee_payment_{id}>
			</groups>
		</payment>
	</sections>
</config>